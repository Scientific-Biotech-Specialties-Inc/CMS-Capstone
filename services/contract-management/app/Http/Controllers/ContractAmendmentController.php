<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\ContractStatus;
use App\Models\ContractRegion;
use App\Models\ContractAmendment;
use App\Models\ContractVersionSnapshot;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ContractAmendmentController extends Controller
{
    protected AuditLogService $auditLogService;
    protected AuthService $authService;
    protected NotificationService $notificationService;

    public function __construct(
        AuditLogService $auditLogService,
        AuthService $authService,
        NotificationService $notificationService
    ) {
        $this->auditLogService     = $auditLogService;
        $this->authService         = $authService;
        $this->notificationService = $notificationService;
    }

    /**
     * Resolve a contract code (e.g. 'CTR-JUEIF7') or numeric ID to its integer primary key.
     */
    private function resolveContractId(mixed $id): ?int
    {
        if ($id === null) {
            return null;
        }
        if (is_numeric($id)) {
            return (int) $id;
        }
        if (is_string($id)) {
            $contract = Contract::where('contract_code', $id)->first();
            return $contract?->contract_id;
        }
        return null;
    }

    private function formatAmendment(ContractAmendment $amd): array
    {
        $docs = [];
        if (!empty($amd->document_ids)) {
            $docs = \App\Models\Document::whereIn('_id', $amd->document_ids)->get()->map(fn ($d) => [
                'id'           => (string) ($d->document_id ?? $d->_id),
                'name'         => $d->file_name,
                'type'         => $d->file_type,
                'size'         => $d->file_size,
                'previewUrl'   => $d->document_url,
                'uploadStatus' => 'success',
            ])->values()->toArray();
        }

        return [
            'id'              => 'AMD-' . str_pad($amd->amendment_id, 3, '0', STR_PAD_LEFT),
            'contractId'      => (string) $amd->contract_id,
            'businessPartner' => $amd->bp_name,
            'category'        => $amd->category,
            'itemCode'        => $amd->item_code,
            'description'     => $amd->description,
            'serialNo'        => $amd->serial_number,
            'sbuNumber'       => $amd->sbu_number,
            'region'          => $amd->region,
            'startDate'       => $amd->start_date?->toDateString(),
            'endDate'         => $amd->end_date?->toDateString(),
            'reason'          => $amd->reason,
            'status'          => $amd->status,
            'requestDate'     => $amd->request_date?->toDateString(),
            'version'         => $amd->version,
            'createdBy'       => $amd->creator_name ?? ('User #' . $amd->created_by),
            'approvedBy'      => $amd->approved_by,
            'rejectionReason' => $amd->rejection_reason,
            'docs'            => $docs,
        ];
    }

    public function index(Request $request)
    {
        $role = $request->get('auth_role');
        $userId = $request->get('auth_id');

        $query = ContractAmendment::query();

        if (in_array($role, ['Sales', 'Employee'])) {
            $query->where('created_by', $userId);
        }

        $amendments = $query->orderBy('amendment_id', 'desc')->get();

        // Fetch user details for creator names
        $userIds = $amendments->pluck('created_by')->filter()->unique()->toArray();
        $users = $this->authService->getUsersBatch($userIds);
        $userMap = collect($users)->keyBy('id');

        $amendments->each(function ($a) use ($userMap) {
            if ($a->created_by && $userMap->has($a->created_by)) {
                $u = $userMap->get($a->created_by);
                $a->creator_name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            }
        });

        return response()->json([
            'data' => $amendments->map(fn ($a) => $this->formatAmendment($a))->values()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->all();

        // Normalize camelCase fields from frontend
        $normalized = [
            'contract_id'     => $this->resolveContractId($data['contract_id'] ?? $data['contractId'] ?? null),
            'bp_name'         => $data['bp_name'] ?? $data['businessPartner'] ?? null,
            'category'        => $data['category'] ?? null,
            'item_code'       => $data['item_code'] ?? $data['itemCode'] ?? null,
            'description'     => $data['description'] ?? null,
            'serial_number'   => $data['serial_number'] ?? $data['serialNo'] ?? null,
            'sbu_number'      => $data['sbu_number'] ?? $data['sbuNumber'] ?? null,
            'region'          => $data['region'] ?? null,
            'start_date'      => $data['start_date'] ?? $data['startDate'] ?? null,
            'end_date'        => $data['end_date'] ?? $data['endDate'] ?? null,
            'reason'          => $data['reason'] ?? null,
            'document_ids'    => $data['document_ids'] ?? $data['docs'] ?? [],
        ];

        $validator = Validator::make($normalized, [
            'contract_id'     => 'required|integer|exists:contracts,contract_id',
            'bp_name'         => 'required|string|max:255',
            'category'        => 'required|string|max:255',
            'item_code'       => 'required|string|max:255',
            'description'     => 'required|string',
            'serial_number'   => 'required|string|max:255',
            'sbu_number'      => 'required|string|max:255',
            'region'          => 'required|string|in:Luzon,Visayas,Mindanao',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'reason'          => 'required|string',
            'document_ids'    => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $incoming = $validator->validated();
        $userId = $request->get('auth_id');
        $role   = $request->get('auth_role');

        $targetContract = Contract::findOrFail($incoming['contract_id']);
        if (in_array($role, ['Sales', 'Employee'], true) && (int) $targetContract->created_by !== (int) $userId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Extract document IDs (frontend maps docs as objects with IDs)
        $docIds = [];
        if (!empty($data['docs'])) {
            foreach ($data['docs'] as $d) {
                if (is_array($d) && isset($d['id'])) {
                    $docIds[] = $d['id'];
                } elseif (is_string($d)) {
                    $docIds[] = $d;
                }
            }
        }

        // Only allow documents that are unassigned or already linked to this contract
        if (!empty($docIds)) {
            $docIds = \App\Models\Document::whereIn('_id', $docIds)
                ->where(function ($q) use ($incoming) {
                    $q->whereNull('contract_id')
                      ->orWhere('contract_id', $incoming['contract_id']);
                })
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        // Calculate next version
        $approvedCount = ContractAmendment::where('contract_id', $incoming['contract_id'])
            ->where('status', 'Approved')
            ->count();
        $nextVersion = $approvedCount + 2;

        $authUser    = $request->get('auth_user') ?? [];
        $creatorName = trim(($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? ''));
        if (empty($creatorName)) {
            $users = $this->authService->getUsersBatch([$userId]);
            if (!empty($users)) {
                $u = $users[0];
                $creatorName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            }
            if (empty($creatorName)) {
                $creatorName = 'User';
            }
        }

        // Managers and Admins automatically approve their own amendments
        $isManagerRole = in_array($role, ['Manager', 'Admin']);
        $initialStatus = $isManagerRole ? 'Approved' : 'Pending';

        $amd = ContractAmendment::create([
            'contract_id'      => $incoming['contract_id'],
            'bp_name'          => $incoming['bp_name'],
            'category'         => $incoming['category'],
            'item_code'        => $incoming['item_code'],
            'description'      => $incoming['description'],
            'serial_number'    => $incoming['serial_number'],
            'sbu_number'       => $incoming['sbu_number'],
            'region'           => $incoming['region'],
            'start_date'       => $incoming['start_date'],
            'end_date'         => $incoming['end_date'],
            'reason'           => $incoming['reason'],
            'status'           => $initialStatus,
            'request_date'     => now()->toDateString(),
            'version'          => $nextVersion,
            'created_by'       => $userId,
            'document_ids'     => $docIds,
            'approved_by'      => $isManagerRole ? $creatorName : null,
        ]);

        // Audit Logging – submission
        $this->auditLogService->log(
            'amendment_submitted',
            'ContractAmendment',
            $amd->amendment_id,
            $userId,
            [],
            $amd->toArray(),
            $request->get('auth_department')
        );

        // ── Auto-approval for Manager/Admin ───────────────────────────────────
        if ($isManagerRole) {
            $contract = Contract::with(['category', 'region', 'documents'])->findOrFail($amd->contract_id);

            // 1. Snapshot the current contract state BEFORE applying changes
            $docsMap = $contract->documents->map(fn ($d) => [
                'id'         => (string) ($d->document_id ?? $d->_id),
                'name'       => $d->file_name,
                'type'       => $d->file_type,
                'size'       => $d->file_size,
                'previewUrl' => $d->document_url,
            ])->values()->toArray();

            ContractVersionSnapshot::create([
                'contract_id'   => $contract->contract_id,
                'version'       => $amd->version - 1, // Snapshot captures prior active state
                'bp_name'       => $contract->bp_name,
                'category'      => $contract->category?->category_name ?? '',
                'item_code'     => $contract->item_code,
                'description'   => $contract->description,
                'serial_number' => $contract->serial_number,
                'sbu_number'    => $contract->sbu_number,
                'region'        => $contract->region?->region_name ?? 'Luzon',
                'start_date'    => $contract->start_date,
                'end_date'      => $contract->end_date,
                'reason'        => $amd->reason,
                'amended_by'    => $creatorName,
                'approved_by'   => $creatorName,
                'approved_date' => now()->toDateString(),
                'docs'          => $docsMap,
            ]);

            // 2. Resolve Category & Region IDs for updating contract
            $cat      = ContractCategory::firstOrCreate(['category_name' => $amd->category]);
            $regionId = DB::table('contract_regions')
                ->where('region_name', $amd->region)
                ->value('region_id') ?: 1;

            // 3. Apply amendment details to the live Contract record
            $contract->update([
                'category_id'   => $cat->category_id,
                'bp_name'       => $amd->bp_name,
                'item_code'     => $amd->item_code,
                'description'   => $amd->description,
                'serial_number' => $amd->serial_number,
                'sbu_number'    => $amd->sbu_number,
                'region_id'     => $regionId,
                'start_date'    => $amd->start_date,
                'end_date'      => $amd->end_date,
            ]);

            // 4. Reconcile document mappings:
            //    - Collect which IDs are currently on the contract
            //    - Unlink any that were removed in this amendment
            //    - Link any kept or newly added documents
            $existingDocIds = $contract->documents
                ->map(fn ($d) => (string)($d->document_id ?? $d->_id))
                ->toArray();
            $amendmentDocIds = array_map('strval', $amd->document_ids ?? []);

            $removedDocIds = array_diff($existingDocIds, $amendmentDocIds);
            if (!empty($removedDocIds)) {
                \App\Models\Document::whereIn('_id', array_values($removedDocIds))
                    ->update(['contract_id' => null]);
            }
            if (!empty($amendmentDocIds)) {
                $linkableDocIds = \App\Models\Document::whereIn('_id', $amendmentDocIds)
                    ->where(function ($q) use ($contract) {
                        $q->whereNull('contract_id')
                          ->orWhere('contract_id', $contract->contract_id);
                    })
                    ->pluck('_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();
                if (!empty($linkableDocIds)) {
                    \App\Models\Document::whereIn('_id', $linkableDocIds)
                        ->update(['contract_id' => $contract->contract_id]);
                }
            }

            // Audit Log – approval
            $this->auditLogService->log(
                'amendment_approved',
                'ContractAmendment',
                $amd->amendment_id,
                $userId,
                [],
                $amd->toArray(),
                $request->get('auth_department')
            );
        }
        // ─────────────────────────────────────────────────────────────────────

        $amd->creator_name = $creatorName;

        $message = $isManagerRole
            ? 'Amendment approved and applied automatically.'
            : 'Amendment request submitted successfully.';

        // Notify all managers when an amendment is submitted
        if (!$isManagerRole) {
            try {
                $contractId = (int) $amd->contract_id;
                $contractName = trim("{$amd->bp_name} ({$amd->item_code})");
                $notifMsg   = "{$creatorName} submitted an amendment request for {$contractName}.";
                $this->notificationService->push($contractId, 'amendment_submitted', $notifMsg, 'Manager,Admin');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Amendment submitted notification failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => $message,
            'data'    => $this->formatAmendment($amd)
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $idInt = (int) str_replace('AMD-', '', $id);
        $amd = ContractAmendment::findOrFail($idInt);

        // Authorization check
        $role = $request->get('auth_role');
        $userId = $request->get('auth_id');
        if (in_array($role, ['Sales', 'Employee']) && $amd->created_by !== $userId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Resolve creator name
        $users = $this->authService->getUsersBatch([$amd->created_by]);
        if (!empty($users)) {
            $u = $users[0];
            $amd->creator_name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        }

        return response()->json([
            'data' => $this->formatAmendment($amd)
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $idInt = (int) str_replace('AMD-', '', $id);
        $amd = ContractAmendment::findOrFail($idInt);

        // Salesperson unsubmit authorization
        $role = $request->get('auth_role');
        $userId = $request->get('auth_id');
        if (in_array($role, ['Sales', 'Employee']) && $amd->created_by !== $userId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($amd->status !== 'Pending') {
            return response()->json(['message' => 'Cannot delete a reviewed amendment request.'], 422);
        }

        $oldData = $amd->toArray();
        $amd->delete();

        // Audit Logging
        $this->auditLogService->log(
            'amendment_deleted',
            'ContractAmendment',
            $idInt,
            $userId,
            $oldData,
            [],
            $request->get('auth_department')
        );

        return response()->json(['message' => 'Amendment request deleted successfully.']);
    }

    public function updateStatus(Request $request, $id)
    {
        $idInt = (int) str_replace('AMD-', '', $id);
        $amd = ContractAmendment::findOrFail($idInt);

        $userId = $request->get('auth_id');

        // Separation of duties: the person who requested an amendment cannot
        // also approve or reject it. Combined with permission:cms.contracts.approve
        // on the route, this is the four-eyes rule for amendments.
        if ($amd->created_by !== null && (int) $amd->created_by === (int) $userId) {
            return response()->json([
                'message' => 'You cannot review your own amendment request.',
            ], 403);
        }

        $request->validate([
            'status' => 'required|string|in:Approved,Rejected',
            'rejection_reason' => 'required_if:status,Rejected|nullable|string',
        ]);

        $status = $request->status;
        $rejectionReason = $request->rejection_reason;
        
        $authUser = $request->get('auth_user') ?? [];
        $managerName = trim(($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? ''));
        if (empty($managerName)) {
            $users = $this->authService->getUsersBatch([$userId]);
            if (!empty($users)) {
                $u = $users[0];
                $managerName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            }
            if (empty($managerName)) {
                $managerName = 'Manager';
            }
        }

        if ($amd->status !== 'Pending') {
            return response()->json(['message' => 'Amendment is already reviewed.'], 422);
        }

        if ($status === 'Approved') {
            $contract = Contract::with(['category', 'region', 'documents'])->findOrFail($amd->contract_id);

            // Fetch user detail for snapshot amended_by
            $users = $this->authService->getUsersBatch([$amd->created_by]);
            $creatorName = 'Salesperson';
            if (!empty($users)) {
                $u = $users[0];
                $creatorName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            }

            // 1. Create a Snapshot of current contract state BEFORE updates
            $docsMap = $contract->documents->map(fn ($d) => [
                'id'         => (string) ($d->document_id ?? $d->_id),
                'name'       => $d->file_name,
                'type'       => $d->file_type,
                'size'       => $d->file_size,
                'previewUrl' => $d->document_url,
            ])->values()->toArray();

            ContractVersionSnapshot::create([
                'contract_id'   => $contract->contract_id,
                'version'       => $amd->version - 1, // Snapshot captures previous active state
                'bp_name'       => $contract->bp_name,
                'category'      => $contract->category?->category_name ?? '',
                'item_code'     => $contract->item_code,
                'description'   => $contract->description,
                'serial_number' => $contract->serial_number,
                'sbu_number'    => $contract->sbu_number,
                'region'        => $contract->region?->region_name ?? 'Luzon',
                'start_date'    => $contract->start_date,
                'end_date'      => $contract->end_date,
                'reason'        => $amd->reason,
                'amended_by'    => $creatorName,
                'approved_by'   => $managerName,
                'approved_date' => now()->toDateString(),
                'docs'          => $docsMap,
            ]);

            // 2. Resolve Category & Region IDs for updating contract
            $cat = ContractCategory::firstOrCreate(['category_name' => $amd->category]);
            $regionId = DB::table('contract_regions')
                ->where('region_name', $amd->region)
                ->value('region_id') ?: 1;

            // 3. Apply Amendment details to real Contract record
            $contract->update([
                'category_id'   => $cat->category_id,
                'bp_name'       => $amd->bp_name,
                'item_code'     => $amd->item_code,
                'description'   => $amd->description,
                'serial_number' => $amd->serial_number,
                'sbu_number'    => $amd->sbu_number,
                'region_id'     => $regionId,
                'start_date'    => $amd->start_date,
                'end_date'      => $amd->end_date,
            ]);

            // 4. Reconcile document mappings:
            //    - Collect which IDs are currently on the contract
            //    - Unlink any that were removed in this amendment
            //    - Link any kept or newly added documents
            $existingDocIds = $contract->documents
                ->map(fn ($d) => (string)($d->document_id ?? $d->_id))
                ->toArray();
            $amendmentDocIds = array_map('strval', $amd->document_ids ?? []);

            $removedDocIds = array_diff($existingDocIds, $amendmentDocIds);
            if (!empty($removedDocIds)) {
                \App\Models\Document::whereIn('_id', array_values($removedDocIds))
                    ->update(['contract_id' => null]);
            }
            if (!empty($amendmentDocIds)) {
                $linkableDocIds = \App\Models\Document::whereIn('_id', $amendmentDocIds)
                    ->where(function ($q) use ($contract) {
                        $q->whereNull('contract_id')
                          ->orWhere('contract_id', $contract->contract_id);
                    })
                    ->pluck('_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();
                if (!empty($linkableDocIds)) {
                    \App\Models\Document::whereIn('_id', $linkableDocIds)
                        ->update(['contract_id' => $contract->contract_id]);
                }
            }

            // 5. Update Amendment status
            $amd->update([
                'status'      => 'Approved',
                'approved_by' => $managerName,
            ]);

            // Audit Log
            $this->auditLogService->log(
                'amendment_approved',
                'ContractAmendment',
                $amd->amendment_id,
                $userId,
                [],
                $amd->toArray(),
                $request->get('auth_department')
            );

            // Notify the amendment creator that their request was approved
            try {
                $contractId = (int) $amd->contract_id;
                $contractName = trim("{$amd->bp_name} ({$amd->item_code})");
                $notifMsg   = "Your amendment request for {$contractName} has been Approved by {$managerName}.";
                $this->notificationService->push($contractId, 'amendment_approved', $notifMsg, 'Sales,Employee,Manager,Admin', (int) $amd->created_by);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Amendment approved notification failed: ' . $e->getMessage());
            }
        } else {
            // Rejection
            $amd->update([
                'status'           => 'Rejected',
                'approved_by'      => $managerName,
                'rejection_reason' => $rejectionReason,
            ]);

            // Audit Log
            $this->auditLogService->log(
                'amendment_rejected',
                'ContractAmendment',
                $amd->amendment_id,
                $userId,
                [],
                $amd->toArray(),
                $request->get('auth_department')
            );

            // Notify the amendment creator that their request was rejected
            try {
                $contractId = (int) $amd->contract_id;
                $contractName = trim("{$amd->bp_name} ({$amd->item_code})");
                $notifMsg   = "Your amendment request for {$contractName} has been Rejected by {$managerName}.";
                $this->notificationService->push($contractId, 'amendment_rejected', $notifMsg, 'Sales,Employee,Manager,Admin', (int) $amd->created_by);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Amendment rejected notification failed: ' . $e->getMessage());
            }
        }

        // Resolve creator name
        $users = $this->authService->getUsersBatch([$amd->created_by]);
        if (!empty($users)) {
            $u = $users[0];
            $amd->creator_name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        }

        return response()->json([
            'message' => 'Amendment status updated successfully.',
            'data'    => $this->formatAmendment($amd),
        ]);
    }

    public function versionHistory(Request $request, $contractId)
    {
        $contract = \App\Models\Contract::where('contract_code', $contractId)
            ->orWhere('contract_id', $contractId)
            ->first();

        if ($contract) {
            $role = $request->get('auth_role');
            $userId = $request->get('auth_id');
            if (in_array($role, ['Sales', 'Employee'], true) && (int) $contract->created_by !== (int) $userId) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $numericId = $contract ? $contract->contract_id : $contractId;

        $snapshots = ContractVersionSnapshot::where('contract_id', $numericId)
            ->orderBy('version', 'desc')
            ->get();

        return response()->json([
            'data' => $snapshots->map(fn ($s) => [
                'version'         => $s->version,
                'businessPartner' => $s->bp_name,
                'category'        => $s->category,
                'itemCode'        => $s->item_code,
                'description'     => $s->description,
                'serialNo'        => $s->serial_number,
                'sbuNumber'       => $s->sbu_number,
                'region'          => $s->region,
                'startDate'       => $s->start_date?->toDateString(),
                'endDate'         => $s->end_date?->toDateString(),
                'reason'          => $s->reason,
                'amendedBy'       => $s->amended_by,
                'approvedBy'      => $s->approved_by,
                'approvedDate'    => $s->approved_date?->toDateString(),
                'docs'            => $s->docs ?? [],
            ])->values()
        ]);
    }
}
