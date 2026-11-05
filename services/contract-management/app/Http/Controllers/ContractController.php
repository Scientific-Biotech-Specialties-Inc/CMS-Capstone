<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\ContractStatus;
use App\Models\ContractApprovalStatus;
use App\Models\ContractRegion;
use App\Models\ApprovalInstance;
use App\Models\WorkflowStepRole;
use App\Services\AuditLogService;
use App\Services\ApprovalEngineService;
use App\Jobs\SyncContractToMeilisearch;
use App\Jobs\RemoveContractFromMeilisearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContractController extends Controller
{
    protected AuditLogService $auditLogService;
    protected \App\Services\AuthService $authService;
    protected ApprovalEngineService $approvalEngine;

    public function __construct(
        AuditLogService $auditLogService,
        \App\Services\AuthService $authService,
        ApprovalEngineService $approvalEngine
    ) {
        $this->auditLogService = $auditLogService;
        $this->authService = $authService;
        $this->approvalEngine = $approvalEngine;
    }

    private function formatContract(Contract $contract): array
    {
        return [
            'contract_id'     => $contract->contract_code ?? (string) $contract->contract_id,
            'contract_db_id'  => $contract->contract_id,
            'contract_code'   => $contract->contract_code,
            'bp_name'         => $contract->bp_name,
            'category'        => $contract->category?->category_name,
            'approval_status' => $contract->approvalStatus?->status_name,
            'workflow_status' => $contract->workflowStatus?->status_name,
            'item_code'       => $contract->item_code,
            'description'     => $contract->description,
            'serial_number'   => $contract->serial_number,
            'sbu_number'      => $contract->sbu_number,
            'region'          => $contract->region?->region_name,
            'rejection_reason' => $contract->rejection_reason,
            'start_date'       => $contract->start_date?->toDateString(),
            'end_date'         => $contract->end_date?->toDateString(),
            'lifecycle_status' => $contract->lifecycle_status,
            'created_by'       => $contract->created_by,
            'creator_name'     => $contract->creator_name ?? null,
            'prs_activity_id'  => $contract->prs_activity_id,
            'created_at'       => $contract->created_at?->toISOString(),
            'documents'        => $contract->documents->map(fn ($d) => [
                'document_id'  => (string) ($d->document_id ?? $d->_id),
                'file_name'    => $d->file_name,
                'file_type'    => $d->file_type,
                'file_size'    => $d->file_size,
                'document_url' => $d->document_url,
            ])->values(),
        ];
    }

    public function index(Request $request)
    {
        $query = Contract::with([
            'documents',
            'category',
            'approvalStatus',
            'workflowStatus',
            'region',
        ]);

        if ($request->has('search') && !empty($request->search)) {
            // Bound the term length: unbounded LIKE patterns are a cheap DoS vector.
            $search = mb_substr((string) $request->search, 0, 255);
            $query->where(function($q) use ($search) {
                $q->where('bp_name', 'like', $search . '%')
                  ->orWhere('item_code', 'like', $search . '%')
                  ->orWhere('contract_id', 'like', $search . '%')
                  ->orWhere('contract_code', 'like', $search . '%');
            });
        }

        if ($request->has('category') && !empty($request->category)) {
            $category = $request->category;
            $query->whereHas('category', function($q) use ($category) {
                $q->where('category_name', $category);
            });
        }

        if ($request->has('region') && !empty($request->region)) {
            $region = $request->region;
            $query->whereHas('region', function($q) use ($region) {
                $q->where('region_name', $region);
            });
        }

        if ($request->has('status') && !empty($request->status)) {
            $status = $request->status;
            $query->where(function($q) use ($status) {
                $q->whereHas('approvalStatus', function($sq) use ($status) {
                    $sq->where('status_name', $status);
                })->orWhereHas('workflowStatus', function($sq) use ($status) {
                    $sq->where('status_name', $status);
                });
            });
        }

        if ($request->has('lifecycle_status') && !empty($request->lifecycle_status)) {
            $lifecycle = $request->lifecycle_status;
            $today = now()->toDateString();
            $thirtyDaysFromNow = now()->addDays(30)->toDateString();
            if ($lifecycle === 'active') {
                $query->where('approval_status_id', 2)
                      ->where(function($q) use ($thirtyDaysFromNow) {
                          $q->whereNull('end_date')
                            ->orWhere('end_date', '>', $thirtyDaysFromNow);
                      });
            } elseif ($lifecycle === 'inactive') {
                $query->where(function($q) {
                    $q->whereNull('approval_status_id')
                      ->orWhere('approval_status_id', '!=', 2);
                });
            } elseif ($lifecycle === 'expiring') {
                $query->where('approval_status_id', 2)
                      ->whereBetween('end_date', [$today, $thirtyDaysFromNow]);
            } elseif ($lifecycle === 'expired') {
                $query->where('approval_status_id', 2)
                      ->where('end_date', '<', $today);
            }
        }

        // Sales and Employee (incl. Sales & Marketing dept) can only ever see their own contracts
        $role = $request->get('auth_role');
        if (in_array($role, ['Sales', 'Employee'])) {
            $query->where('created_by', $request->get('auth_id'));
        }

        // Rejected contracts (approval_status_id = 3) always appear at the bottom of the list
        $query->orderByRaw('CASE WHEN approval_status_id = 3 THEN 1 ELSE 0 END ASC')
              ->orderBy('contract_id', 'desc');

        if ($request->has('paginate') && $request->paginate === 'true') {
            // Cap page size: existing clients send up to 100, so this is the
            // highest value that does not change current behaviour.
            $perPage = max(1, min((int) $request->get('per_page', 10), 100));
            $paginated = $query->paginate($perPage);

            // Calculate stats based on role permissions
            $today = now()->toDateString();
            $thirtyDays = now()->addDays(30)->toDateString();

            $statsQuery = Contract::query();
            if (in_array($role, ['Sales', 'Employee'])) {
                $statsQuery->where('created_by', $request->get('auth_id'));
            }

            $stats = [
                'total'    => (int) (clone $statsQuery)->count(),
                'active'   => (int) (clone $statsQuery)->where('approval_status_id', 2)->where(function($q) use ($thirtyDays) {
                    $q->whereNull('end_date')->orWhere('end_date', '>', $thirtyDays);
                })->count(),
                'inactive' => (int) (clone $statsQuery)->where(function($q) {
                    $q->whereNull('approval_status_id')->orWhere('approval_status_id', '!=', 2);
                })->count(),
                'expiring' => (int) (clone $statsQuery)->where('approval_status_id', 2)->whereBetween('end_date', [$today, $thirtyDays])->count(),
                'expired'  => (int) (clone $statsQuery)->where('approval_status_id', 2)->where('end_date', '<', $today)->count(),
            ];

            $contractsCollection = collect($paginated->items());
            
            $userIds = $contractsCollection->pluck('created_by')->filter()->unique()->toArray();
            $users = $this->authService->getUsersBatch($userIds);
            $userMap = collect($users)->keyBy('id');

            $contractsCollection->each(function ($c) use ($userMap) {
                if ($c->created_by && $userMap->has($c->created_by)) {
                    $u = $userMap->get($c->created_by);
                    $c->creator_name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                }
            });

            return response()->json([
                'data' => $contractsCollection->map(fn ($c) => $this->formatContract($c))->values(),
                'pagination' => [
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'stats' => $stats
            ]);
        }

        $contracts = $query->get();

        $userIds = $contracts->pluck('created_by')->filter()->unique()->toArray();
        $users = $this->authService->getUsersBatch($userIds);
        $userMap = collect($users)->keyBy('id');

        $contracts->each(function ($c) use ($userMap) {
            if ($c->created_by && $userMap->has($c->created_by)) {
                $u = $userMap->get($c->created_by);
                $c->creator_name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            }
        });

        return response()->json([
            'data' => $contracts->map(fn ($c) => $this->formatContract($c))->values(),
        ]);
    }

    private function vendorIsSuspended(string $bpName): bool
    {
        if (Schema::hasTable('business_partners')) {
            $partner = DB::table('business_partners')->where('partner_name', $bpName)->first();
            if ($partner && $partner->status === 'Suspended') {
                return true;
            }
        }

        if (Schema::hasTable('suppliers')) {
            $supplier = DB::table('suppliers')->where('supplier_name', $bpName)->first();
            if ($supplier && $supplier->status === 'Suspended') {
                return true;
            }
        }

        return false;
    }

    private function autoLinkVendor(int $contractId, string $bpName, ?int $userId): void
    {
        if (!Schema::hasTable('vendor_contract_associations')) {
            return;
        }

        // 1. Try to find in business_partners
        if (Schema::hasTable('business_partners')) {
            $partner = DB::table('business_partners')
                ->where('partner_name', $bpName)
                ->first();

            if ($partner) {
                DB::table('vendor_contract_associations')->updateOrInsert(
                    [
                        'vendor_type' => 'partner',
                        'vendor_id'   => $partner->partner_id,
                        'contract_id' => $contractId,
                    ],
                    [
                        'attached_by' => $userId,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]
                );
                return;
            }
        }

        // 2. Try to find in suppliers
        if (Schema::hasTable('suppliers')) {
            $supplier = DB::table('suppliers')
                ->where('supplier_name', $bpName)
                ->first();

            if ($supplier) {
                DB::table('vendor_contract_associations')->updateOrInsert(
                    [
                        'vendor_type' => 'supplier',
                        'vendor_id'   => $supplier->supplier_id,
                        'contract_id' => $contractId,
                    ],
                    [
                        'attached_by' => $userId,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]
                );
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        // Normalize camelCase fields from test to snake_case
        $normalized = [
            'bp_name'         => $data['bp_name'] ?? $data['businessPartner'] ?? null,
            'category'        => $data['category'] ?? null,
            'status'          => $data['status'] ?? $data['workflow_status'] ?? null,
            'item_code'       => $data['item_code'] ?? $data['itemCode'] ?? null,
            'description'     => $data['description'] ?? null,
            'serial_number'   => $data['serial_number'] ?? $data['serialNo'] ?? null,
            'sbu_number'      => $data['sbu_number'] ?? $data['sbuNumber'] ?? null,
            'region'          => $data['region'] ?? null,
            'start_date'      => $data['start_date'] ?? $data['startDate'] ?? null,
            'end_date'        => $data['end_date'] ?? $data['endDate'] ?? null,
            'document_ids'    => $data['document_ids'] ?? null,
            'prs_activity_id' => $data['prs_activity_id'] ?? null,
        ];

        $validator = Validator::make($normalized, [
            'bp_name'         => 'required|string|max:255',
            'category'        => 'required|string|max:255',
            'status'          => 'nullable|string|max:255',
            'item_code'       => 'required|string|max:255',
            'description'     => 'required|string',
            'serial_number'   => 'required|string|max:255|unique:contracts,serial_number',
            'sbu_number'      => 'required|string|max:255',
            'region'          => 'required|string|in:Luzon,Visayas,Mindanao',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'document_ids'    => 'nullable|array',
            'document_ids.*'  => 'string',
            'prs_activity_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $incoming = $validator->validated();

        if ($this->vendorIsSuspended($incoming['bp_name'])) {
            return response()->json([
                'message' => 'This business partner/supplier is suspended and cannot be assigned to a new contract.'
            ], 422);
        }

        // Resolve IDs
        $cat = ContractCategory::firstOrCreate(['category_name' => $incoming['category']]);
        $regionId = DB::table('contract_regions')
            ->where('region_name', $incoming['region'])
            ->value('region_id');

        if (!$regionId) {
            return response()->json(['message' => 'Invalid region.'], 422);
        }

        $userId = $request->get('auth_id');
        $role = $request->get('auth_role');
        $isManagerRole = in_array($role, ['Manager', 'Admin']);

        // Dynamic Approval Workflow Engine (Phase 5): if this contract type
        // has an active workflow, it must start out Pending regardless of
        // who created it — the engine (not the creator's role) decides the
        // outcome. Only applies when a chain actually exists; categories
        // without one keep the legacy manager-auto-approve behavior.
        // Resolved once here and reused below (store()) to avoid a second
        // round-trip for the same lookup.
        $activeWorkflow = $this->approvalEngine->isEnabled()
            ? $this->approvalEngine->resolveActiveWorkflow((int) $cat->category_id)
            : null;
        $routedByEngine = $activeWorkflow !== null;

        // Resolve Approval Status and Workflow status
        if ($isManagerRole && !$routedByEngine) {
            $approvalStatusName = 'Approved';
            $workflowStatusName = $incoming['status'] ?? 'SBSI Review';
        } else {
            $approvalStatusName = 'Pending';
            $workflowStatusName = $routedByEngine ? null : ($incoming['status'] ?? null);
        }

        $approvalStatus = ContractApprovalStatus::firstOrCreate(['status_name' => $approvalStatusName]);
        
        $workflowStatusId = null;
        if ($workflowStatusName) {
            $stat = ContractStatus::firstOrCreate(['status_name' => $workflowStatusName]);
            $workflowStatusId = $stat->status_id;
        }

        $contract = Contract::create([
            'category_id'        => $cat->category_id,
            'approval_status_id' => $approvalStatus->approval_status_id,
            'workflow_status_id' => $workflowStatusId,
            'bp_name'            => $incoming['bp_name'],
            'item_code'          => $incoming['item_code'],
            'description'        => $incoming['description'],
            'serial_number'      => $incoming['serial_number'],
            'sbu_number'         => $incoming['sbu_number'],
            'region_id'          => $regionId,
            'start_date'         => $incoming['start_date'],
            'end_date'           => $incoming['end_date'],
            'created_by'         => $userId,
            'prs_activity_id'    => $incoming['prs_activity_id'] ?? null,
        ]);

        // Link MongoDB documents (only unlinked / orphan documents to prevent document hijacking)
        if (!empty($incoming['document_ids'])) {
            $linkableIds = \App\Models\Document::whereIn('_id', $incoming['document_ids'])
                ->whereNull('contract_id')
                ->get()
                ->map(fn ($doc) => (string) $doc->getKey())
                ->all();

            if (!empty($linkableIds)) {
                \App\Models\Document::whereIn('_id', $linkableIds)
                    ->update(['contract_id' => $contract->contract_id]);

                // Audit log each linked document
                foreach ($linkableIds as $docId) {
                    $this->auditLogService->log(
                        'document_linked',
                        'Document',
                        $docId,
                        $userId,
                        [],
                        ['contract_id' => $contract->contract_id],
                        $request->get('auth_department')
                    );
                }
            }
        }

        // Auto-link to business partner/supplier if exists
        $this->autoLinkVendor((int) $contract->contract_id, $contract->bp_name, (int) $userId);

        // Audit Logging
        $this->auditLogService->log(
            'created',
            'Contract',
            $contract->contract_id,
            $userId,
            [],
            $contract->toArray(),
            $request->get('auth_department')
        );

        $contract->load(['documents', 'category', 'approvalStatus', 'workflowStatus', 'region']);

        // Dynamic Approval Workflow Engine (Phase 5) — additive only. If
        // disabled, or the contract's category has no ACTIVE workflow,
        // this is a no-op and the contract keeps the legacy
        // Approved/Pending status set above (decision #9's fallback).
        if ($activeWorkflow) {
            $this->approvalEngine->startInstance($contract, $activeWorkflow);
            $contract->refresh()->load(['documents', 'category', 'approvalStatus', 'workflowStatus', 'region']);
        }

        SyncContractToMeilisearch::dispatch($this->formatContract($contract));

        // Notify Manager if an Employee in Sales & Marketing department creates a contract
        if ($role === 'Employee' && $request->get('auth_department') === 'Sales & Marketing') {
            $authUser = $request->get('auth_user');
            $userName = trim(($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? ''));
            if (empty($userName)) {
                $userName = $authUser['username'] ?? 'A Sales & Marketing Employee';
            }
            $notifMessage = "{$userName} sent a contract and is requesting to review it.";

            try {
                app(\App\Services\NotificationService::class)->push(
                    (int) $contract->contract_id,
                    'sales_marketing_review',
                    $notifMessage,
                    'Manager'
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to push sales & marketing review notification: " . $e->getMessage());
            }
        }

        $this->notifyPrsOfContractLink($contract);

        return response()->json([
            'message' => 'Contract created successfully.',
            'data'    => $this->formatContract($contract)
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $contract = Contract::where('contract_code', $id)
            ->orWhere('contract_id', is_numeric($id) ? (int) $id : 0)
            ->first();

        if (!$contract) {
            return response()->json(['message' => 'Contract not found.'], 404);
        }

        // Authorization check if Sales / Employee editing another user's contract
        $role = $request->get('auth_role');
        $userId = $request->get('auth_id');
        if (in_array($role, ['Sales', 'Employee']) && $contract->created_by !== $userId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->all();

        // Normalize
        $normalized = [
            'bp_name'         => $data['bp_name'] ?? $data['businessPartner'] ?? null,
            'category'        => $data['category'] ?? null,
            'status'          => $data['status'] ?? $data['workflow_status'] ?? null,
            'item_code'       => $data['item_code'] ?? $data['itemCode'] ?? null,
            'description'     => $data['description'] ?? null,
            'serial_number'   => $data['serial_number'] ?? $data['serialNo'] ?? null,
            'sbu_number'      => $data['sbu_number'] ?? $data['sbuNumber'] ?? null,
            'region'          => $data['region'] ?? null,
            'start_date'      => $data['start_date'] ?? $data['startDate'] ?? null,
            'end_date'        => $data['end_date'] ?? $data['endDate'] ?? null,
            'document_ids'    => $data['document_ids'] ?? null,
        ];

        $validator = Validator::make($normalized, [
            'bp_name'         => 'required|string|max:255',
            'category'        => 'required|string|max:255',
            'status'          => 'nullable|string|max:255',
            'item_code'       => 'required|string|max:255',
            'description'     => 'required|string',
            'serial_number'   => "required|string|max:255|unique:contracts,serial_number,{$contract->contract_id},contract_id",
            'sbu_number'      => 'required|string|max:255',
            'region'          => 'required|string|in:Luzon,Visayas,Mindanao',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'document_ids'    => 'nullable|array',
            'document_ids.*'  => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $incoming = $validator->validated();

        if ($incoming['bp_name'] !== $contract->bp_name && $this->vendorIsSuspended($incoming['bp_name'])) {
            return response()->json([
                'message' => 'This business partner/supplier is suspended and cannot be assigned to a new contract.'
            ], 422);
        }

        $cat = ContractCategory::firstOrCreate(['category_name' => $incoming['category']]);
        $regionId = DB::table('contract_regions')
            ->where('region_name', $incoming['region'])
            ->value('region_id');

        if (!$regionId) {
            return response()->json(['message' => 'Invalid region.'], 422);
        }

        $oldData = $contract->toArray();

        $updatePayload = [
            'category_id'   => $cat->category_id,
            'bp_name'       => $incoming['bp_name'],
            'item_code'     => $incoming['item_code'],
            'description'   => $incoming['description'],
            'serial_number' => $incoming['serial_number'],
            'sbu_number'    => $incoming['sbu_number'],
            'region_id'     => $regionId,
            'start_date'    => $incoming['start_date'],
            'end_date'      => $incoming['end_date'],
        ];

        if (!empty($incoming['status'])) {
            $stat = ContractStatus::firstOrCreate(['status_name' => $incoming['status']]);
            $updatePayload['workflow_status_id'] = $stat->status_id;
        }

        // Reset approval status to pending if the contract was rejected and is being edited by the employee/sales owner
        $wasRejected = $contract->approvalStatus?->status_name === 'Rejected';
        if (in_array($role, ['Sales', 'Employee']) && $wasRejected) {
            $pendingStatus = ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
            $updatePayload['approval_status_id'] = $pendingStatus->approval_status_id;
        }

        $contract->update($updatePayload);

        // Dynamic Approval Workflow Engine (Phase 5): if this contract has
        // a prior rejected run through the engine, resubmitting creates a
        // new ApprovalInstance per the workflow's resubmit_mode (restart
        // at step 1, or resume at the step that rejected). No-op for
        // contracts never routed through the engine, or whose latest run
        // isn't in revisions_required.
        if ($wasRejected && $this->approvalEngine->isEnabled()) {
            $this->approvalEngine->resubmit($contract->refresh());
        }

        // Link MongoDB documents and delete removed ones
        $incomingDocIds = $incoming['document_ids'] ?? [];
        
        $currentDocs = \App\Models\Document::where('contract_id', $contract->contract_id)->get();
        foreach ($currentDocs as $doc) {
            $docIdStr = (string)$doc->getKey();
            if (!in_array($docIdStr, $incomingDocIds)) {
                if ($doc->file_path) {
                    $disk = config('filesystems.default', 'local');
                    \Illuminate\Support\Facades\Storage::disk($disk)->delete($doc->file_path);
                }
                
                $oldDocData = $doc->toArray();
                $doc->delete();
                
                $this->auditLogService->log(
                    'document_deleted',
                    'Document',
                    $docIdStr,
                    $userId,
                    $oldDocData,
                    []
                );
            }
        }

        if (!empty($incomingDocIds)) {
            $linkableIds = \App\Models\Document::whereIn('_id', $incomingDocIds)
                ->where(function ($q) use ($contract) {
                    $q->whereNull('contract_id')
                      ->orWhere('contract_id', $contract->contract_id);
                })
                ->get()
                ->map(fn ($doc) => (string) $doc->getKey())
                ->all();

            if (!empty($linkableIds)) {
                \App\Models\Document::whereIn('_id', $linkableIds)
                    ->update(['contract_id' => $contract->contract_id]);

                // Audit log only newly linked documents (not ones that were already linked)
                $previouslyLinkedIds = $currentDocs->map(fn ($d) => (string) $d->getKey())->toArray();
                foreach ($linkableIds as $docId) {
                    if (!in_array($docId, $previouslyLinkedIds)) {
                        $this->auditLogService->log(
                            'document_linked',
                            'Document',
                            $docId,
                            $userId,
                            [],
                            ['contract_id' => $contract->contract_id],
                            $request->get('auth_department')
                        );
                    }
                }
            }
        }

        // Clear old associations and re-link
        if (Schema::hasTable('vendor_contract_associations')) {
            DB::table('vendor_contract_associations')->where('contract_id', $contract->contract_id)->delete();
        }
        $this->autoLinkVendor((int) $contract->contract_id, $contract->bp_name, (int) $userId);

        // Audit Logging
        $this->auditLogService->log(
            'updated',
            'Contract',
            $contract->contract_id,
            $userId,
            $oldData,
            $contract->toArray(),
            $request->get('auth_department')
        );

        $contract->load(['documents', 'category', 'approvalStatus', 'workflowStatus', 'region']);

        $this->notifyPrsOfContractStatus($contract);

        SyncContractToMeilisearch::dispatch($this->formatContract($contract));

        return response()->json([
            'message' => 'Contract updated successfully.',
            'data'    => $this->formatContract($contract),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $contract = Contract::where('contract_code', $id)
            ->orWhere('contract_id', is_numeric($id) ? (int) $id : 0)
            ->first();

        if (!$contract) {
            return response()->json(['message' => 'Contract not found.'], 404);
        }

        $userId = $request->get('auth_id');

        // Row-level authorization: Sales/Employee may only delete contracts
        // they created (mirrors show() / update()). Managers and Admins are
        // unaffected; the route's permission middleware still applies.
        $role = $request->get('auth_role');
        if (in_array($role, ['Sales', 'Employee'], true) && (int) $contract->created_by !== (int) $userId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Delete all associated documents
        $associatedDocs = \App\Models\Document::where('contract_id', $contract->contract_id)->get();
        foreach ($associatedDocs as $doc) {
            if ($doc->file_path) {
                $disk = config('filesystems.default', 'local');
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($doc->file_path);
            }
            
            $oldDocData = $doc->toArray();
            $doc->delete();
            
            $this->auditLogService->log(
                'document_deleted',
                'Document',
                (string)$doc->getKey(),
                $userId,
                $oldDocData,
                []
            );
        }

        $oldData = $contract->toArray();
        $contractId = $contract->contract_id;
        $contract->delete();

        // Audit Logging
        $this->auditLogService->log(
            'deleted',
            'Contract',
            $contractId,
            $userId,
            $oldData,
            [],
            $request->get('auth_department')
        );

        RemoveContractFromMeilisearch::dispatch((int) $contractId);

        return response()->json(['message' => 'Contract deleted successfully.']);
    }

    public function dashboardSummary(Request $request)
    {
        $query = Contract::with([
            'category',
            'approvalStatus',
            'workflowStatus',
            'region',
        ]);

        $role = $request->get('auth_role');
        if (in_array($role, ['Sales', 'Employee'])) {
            $query->where('created_by', $request->get('auth_id'));
        }

        $contracts = $query->orderByRaw('CASE WHEN approval_status_id = 3 THEN 1 ELSE 0 END ASC')
                           ->orderByDesc('contract_id')
                           ->get();

        $userIds = $contracts->pluck('created_by')->filter()->unique()->toArray();
        $users = $this->authService->getUsersBatch($userIds);
        $userMap = collect($users)->keyBy('id');

        return response()->json([
            'data' => $contracts->map(function ($c) use ($userMap) {
                $creatorName = null;
                if ($c->created_by && $userMap->has($c->created_by)) {
                    $u = $userMap->get($c->created_by);
                    $creatorName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                }

                return [
                    'contract_id'     => $c->contract_code ?? (string) $c->contract_id,
                    'contract_db_id'  => $c->contract_id,
                    'contract_code'   => $c->contract_code,
                    'bp_name'         => $c->bp_name,
                    'category'        => $c->category?->category_name,
                    'approval_status' => $c->approvalStatus?->status_name,
                    'workflow_status' => $c->workflowStatus?->status_name,
                    'item_code'       => $c->item_code,
                    'description'     => $c->description,
                    'serial_number'   => $c->serial_number,
                    'sbu_number'      => $c->sbu_number,
                    'region'          => $c->region?->region_name,
                    'start_date'       => $c->start_date?->toDateString(),
                    'end_date'         => $c->end_date?->toDateString(),
                    'lifecycle_status' => $c->lifecycle_status,
                    'created_by'       => $c->created_by,
                    'creator_name'     => $creatorName,
                    'prs_activity_id'  => $c->prs_activity_id,
                    'created_at'       => $c->created_at?->toISOString(),
                    'documents'        => [],
                ];
            })->values(),
        ]);
    }

    public function indexRequests(Request $request)
    {
        $query = Contract::with([
            'documents',
            'category',
            'approvalStatus',
            'workflowStatus',
            'region',
        ]);

        $role = $request->get('auth_role');
        if (in_array($role, ['Sales', 'Employee'])) {
            $query->where('created_by', $request->get('auth_id'));
        } elseif ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('approval_status')) {
            $approvalStatusId = DB::table('contract_approval_statuses')
                ->where('status_name', $request->approval_status)
                ->value('approval_status_id');

            if ($approvalStatusId) {
                $query->where('approval_status_id', $approvalStatusId);
            }
        }

        $contracts = $query->orderByDesc('created_at')->get();

        $userIds = $contracts->pluck('created_by')->filter()->unique()->toArray();
        $users = $this->authService->getUsersBatch($userIds);
        $userMap = collect($users)->keyBy('id');

        $contracts->each(function ($c) use ($userMap) {
            if ($c->created_by && $userMap->has($c->created_by)) {
                $u = $userMap->get($c->created_by);
                $c->creator_name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            }
        });

        return response()->json([
            'data' => $contracts->map(fn ($c) => $this->formatContract($c))->values(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $contract = Contract::with([
            'documents',
            'category',
            'approvalStatus',
            'workflowStatus',
            'region',
        ])->where('contract_code', $id)
          ->orWhere('contract_id', is_numeric($id) ? (int) $id : 0)
          ->firstOrFail();

        $role = $request->get('auth_role');
        if (in_array($role, ['Sales', 'Employee']) && $contract->created_by !== $request->get('auth_id')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($contract->created_by) {
            $users = $this->authService->getUsersBatch([$contract->created_by]);
            if (!empty($users)) {
                $u = $users[0];
                $contract->creator_name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            }
        }

        return response()->json([
            'data' => $this->formatContract($contract),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $contract = Contract::where('contract_code', $id)
            ->orWhere('contract_id', is_numeric($id) ? (int) $id : 0)
            ->firstOrFail();

        // Separation of Duties: a contract creator cannot approve or reject
        // their own contract when enforce_sod is active.
        if (
            config('services.features.enforce_sod')
            && $contract->created_by !== null
            && (int) $contract->created_by === (int) $request->get('auth_id')
        ) {
            return response()->json([
                'message' => 'You cannot approve or reject a contract you created.',
            ], 403);
        }

        // Dynamic Approval Workflow Engine (Phase 5): this legacy endpoint
        // is for the single-manager-approval flow only. A contract with an
        // in-progress (or awaiting-resubmission) engine instance must be
        // actioned via POST /contracts/{id}/approval-tasks/{taskId}/decision
        // instead — throws a 409 pointing there.
        $this->approvalEngine->guardLegacyStatusUpdate((int) $contract->contract_id);

        $request->validate([
            'approval_status' => 'required|string|in:Approved,Rejected',
            'workflow_status' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
        ]);

        // US-023: Mandatory approval gate for High/Critical-risk contracts —
        // DISABLED BY DEFAULT (config('services.features.high_risk_approval_gate_enabled')).
        // AI Risk Assessment is currently advisory only: it surfaces a
        // flag/warning on the contract but never blocks approval. This gate
        // is kept in the codebase, inert, so it can be re-enabled later
        // (set HIGH_RISK_APPROVAL_GATE_ENABLED=true) without further code changes.
        if ($request->approval_status === 'Approved' && config('services.features.high_risk_approval_gate_enabled')) {
            $gateResult = app(\App\Services\HighRiskApprovalGateService::class)
                ->checkGate((int) $contract->contract_id);

            if ($gateResult['blocked']) {
                return response()->json([
                    'message' => 'This contract is High/Critical risk and requires a recorded high-risk approval (with rationale) before it can be marked Approved.',
                    'risk_level' => $gateResult['risk_level'],
                    'requires_high_risk_approval' => true,
                ], 422);
            }
        }

        $approvalStatusId = DB::table('contract_approval_statuses')
            ->where('status_name', $request->approval_status)
            ->value('approval_status_id');

        if (!$approvalStatusId) {
            return response()->json(['message' => 'Invalid approval status.'], 422);
        }

        $workflowStatusId = null;
        if ($request->filled('workflow_status')) {
            $workflowStatusId = DB::table('contract_statuses')
                ->where('status_name', $request->workflow_status)
                ->value('status_id');

            if (!$workflowStatusId) {
                return response()->json(['message' => 'Invalid workflow status.'], 422);
            }
        }

        $updateData = [
            'approval_status_id' => $approvalStatusId,
            'workflow_status_id' => $workflowStatusId,
        ];

        if ($request->approval_status === 'Rejected') {
            $updateData['notify_manager_count'] = 0;
            $updateData['rejection_reason'] = $request->rejection_reason;
        }

        $contract->update($updateData);

        if (in_array($request->approval_status, ['Approved', 'Rejected']) && $contract->created_by) {
            $message = "Your contract request for {$contract->bp_name} ({$contract->item_code}) has been {$request->approval_status}.";
            try {
                app(\App\Services\NotificationService::class)->push(
                    (int) $contract->contract_id,
                    'contract_status_updated',
                    $message,
                    'Employee,Sales',
                    (int) $contract->created_by
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to push contract status notification: " . $e->getMessage());
            }
        }

        $contract->load(['documents', 'category', 'approvalStatus', 'workflowStatus', 'region']);

        $this->notifyPrsOfContractStatus($contract);

        SyncContractToMeilisearch::dispatch($this->formatContract($contract));

        return response()->json([
            'message' => 'Contract status updated.',
            'data'    => $this->formatContract($contract),
        ]);
    }

    private function notifyPrsOfContractLink(Contract $contract): void
    {
        if (empty($contract->prs_activity_id)) {
            return;
        }

        $statusName = $contract->approvalStatus?->status_name ?? 'Pending';
        $outcome = in_array($statusName, ['Approved', 'Rejected'], true)
            ? ($statusName === 'Approved' ? 'Won' : 'Loss')
            : 'Follow-up';

        \App\Jobs\NotifyPrsOfContractUpdateJob::dispatch(
            (int) $contract->prs_activity_id,
            (int) $contract->contract_id,
            $statusName,
            $outcome
        );
    }

    private function notifyPrsOfContractStatus(Contract $contract): void
    {
        if (empty($contract->prs_activity_id)) {
            return;
        }

        $statusName = $contract->approvalStatus?->status_name;
        if (!in_array($statusName, ['Approved', 'Rejected'], true)) {
            return;
        }

        $outcome = $statusName === 'Approved' ? 'Won' : 'Loss';

        \App\Jobs\NotifyPrsOfContractUpdateJob::dispatch(
            (int) $contract->prs_activity_id,
            (int) $contract->contract_id,
            $statusName,
            $outcome
        );
    }

    /**
     * Phase 7 (decision #13): generalized nudge — notifies the role(s)
     * currently responsible for the contract. Engine-routed contracts
     * target every role at the instance's current step (type
     * `approval_step_nudge`); legacy contracts keep the exact historic
     * manager-targeted behavior (`manager_approval_request` → Manager,Admin).
     * Rate limiting reuses the legacy per-contract `notify_manager_count`
     * counter (max 2 → 429); the engine resets it on every step advance
     * in ApprovalEngineService::enterStep(), so each step gets a fresh
     * allowance. Both /notify-step (canonical) and /notify-manager (alias)
     * routes point here.
     */
    public function notifyResponsibleRole(Request $request, $id)
    {
        $contract = Contract::where('contract_code', $id)
            ->orWhere('contract_id', is_numeric($id) ? (int) $id : 0)
            ->firstOrFail();

        if ($contract->notify_manager_count >= 2) {
            return response()->json(['message' => 'The manager is already notified, please wait.'], 429);
        }

        $contract->increment('notify_manager_count');

        $authUser = $request->get('auth_user');
        $userName = trim(($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? ''));
        if (empty($userName)) {
            $userName = $authUser['username'] ?? 'An Employee';
        }

        $instance = ApprovalInstance::where('contract_id', $contract->contract_id)
            ->whereIn('status', ['in_progress', 'revisions_required'])
            ->orderByDesc('run_number')
            ->first();

        $targetRoles = [];
        if ($instance && $instance->current_step_id) {
            $targetRoles = WorkflowStepRole::where('step_id', $instance->current_step_id)
                ->get()
                ->map(fn ($sr) => $this->resolveLiveRoleName($sr))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (!empty($targetRoles)) {
            $targetRolesStr = implode(',', $targetRoles);
            $type = 'approval_step_nudge';
            $message = "Employee {$userName} is requesting approval on Contract {$contract->bp_name} ({$contract->item_code}) — awaiting {$targetRolesStr}.";
            $successMessage = 'Responsible role notified successfully.';
        } else {
            $targetRolesStr = 'Manager,Admin';
            $type = 'manager_approval_request';
            $message = "Employee {$userName} asked for approval on Contract {$contract->bp_name} ({$contract->item_code}).";
            $successMessage = 'Manager notified successfully.';
        }

        try {
            app(\App\Services\NotificationService::class)->push(
                (int) $contract->contract_id,
                $type,
                $message,
                $targetRolesStr
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to push notify responsible-role notification: " . $e->getMessage());
            return response()->json(['message' => 'Failed to notify responsible role.'], 500);
        }

        return response()->json(['message' => $successMessage, 'count' => $contract->notify_manager_count, 'targetRoles' => $targetRolesStr]);
    }

    /**
     * Resolve a step role's LIVE current name for notification targeting
     * (the notification service matches target_roles by exact current
     * role name — a renamed role's stale snapshot would reach nobody).
     * Falls back to the snapshot when auth-module is unreachable.
     */
    private function resolveLiveRoleName(WorkflowStepRole $stepRole): ?string
    {
        try {
            $describe = app(\App\Services\RoleProxyService::class)->describeRole((int) $stepRole->auth_role_id);
            if (!empty($describe['current_name'])) {
                return $describe['current_name'];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Role name resolution failed, using snapshot', [
                'role_id' => $stepRole->auth_role_id,
            ]);
        }

        return $stepRole->role_name_snapshot;
    }

    /**
     * Legacy alias: /notify-manager behaves identically to /notify-step
     * (same method, same rate limit). Kept so cached frontends and the
     * existing NotifyManagerTest keep working untouched.
     */
    public function notifyManager(Request $request, $id)
    {
        return $this->notifyResponsibleRole($request, $id);
    }
}
