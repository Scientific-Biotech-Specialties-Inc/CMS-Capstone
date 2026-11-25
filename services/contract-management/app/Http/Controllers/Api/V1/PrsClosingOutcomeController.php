<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Integrations\SyncPrsProductAction;
use App\Jobs\SyncContractToMeilisearch;
use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use App\Models\ContractStatus;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\PrsContractStatusMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class PrsClosingOutcomeController
{
    public function __invoke(
        Request $request,
        PrsContractStatusMapper $mapper,
        SyncPrsProductAction $productSync,
        NotificationService $notifications,
        AuditLogService $auditLog
    ): JsonResponse {
        $key = (string) (config('services.prs.inbound_key') ?: '');
        $provided = (string) (
            $request->bearerToken()
            ?? $request->header('X-Integration-Secret')
            ?? $request->header('X-Internal-Secret')
            ?? $request->header('X-PRS-Secret')
            ?? ''
        );
        abort_unless($key !== '' && $provided !== '' && hash_equals($key, $provided), 401);

        $validated = $request->validate([
            'event_id'                     => 'nullable|uuid',
            'prs_activity_id'              => 'required_without:activity_id|nullable|integer|min:1',
            'activity_id'                  => 'required_without:prs_activity_id|nullable|integer|min:1',
            'revision'                     => 'nullable|integer|min:1',
            'outcome'                      => 'nullable|string|max:50',
            'prs_status'                   => 'nullable|string|max:50',
            'contract_status'              => 'nullable|string|max:50',
            'contract_id'                  => 'nullable|integer|min:1',
            'contract_code'                => 'nullable|string|max:64',
            'remarks'                      => 'nullable|string|max:2000',
            'rejection_reason'             => 'nullable|string|max:2000',
            'sbsi_branch'                  => 'nullable|string|max:255',
            'region'                       => 'nullable|string|max:100',
            'category'                     => 'nullable|string|max:255',
            'start_date'                   => 'nullable|date',
            'end_date'                     => 'nullable|date',
            'institution'                  => 'nullable|array',
            'institution.institution_name' => 'nullable|string|max:255',
            'institution.bp_code'          => 'nullable|string|max:100',
            'institution.prs_institution_id' => 'nullable|integer|min:1',
            'institution.prs_branch_id'    => 'nullable|integer|min:1',
            'product'                      => 'nullable|array',
            'product.product_name'         => 'nullable|string|max:255',
            'product.product_code'         => 'nullable|string|max:255',
            'product.serial_number'        => 'nullable|string|max:255',
            'product.sbu'                  => 'nullable',
            'product.sbu_number'           => 'nullable|string|max:20',
            'product.description'          => 'nullable|string|max:2000',
            'product.brand'                => 'nullable|string|max:255',
            'product.model'                => 'nullable|string|max:255',
        ]);

        $prsActivityId = (int) ($validated['prs_activity_id'] ?? $validated['activity_id']);
        $revision = (int) ($validated['revision'] ?? 1);
        $eventId = (string) ($validated['event_id'] ?? Str::uuid());
        $rawStatus = $validated['prs_status'] ?? $validated['contract_status'] ?? null;
        $rawOutcome = $validated['outcome'] ?? null;

        if ($rawStatus === null && $rawOutcome === null) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['outcome' => ['Either outcome or prs_status is required.']],
            ], 422);
        }

        if ($rawOutcome !== null && !in_array($rawOutcome, ['Won', 'Loss', 'Follow-up'], true) && $rawStatus === null) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['outcome' => ['The selected outcome is invalid.']],
            ], 422);
        }

        $mapped = $mapper->map($rawStatus, $rawOutcome);

        $existingByEvent = DB::table('prs_closing_events')->where('event_id', $eventId)->first();
        if ($existingByEvent) {
            $same = (int) $existingByEvent->prs_activity_id === $prsActivityId
                && (int) $existingByEvent->revision === $revision
                && $existingByEvent->outcome === $mapped['outcome'];
            abort_unless($same, 409, 'The event ID or activity revision is already in use.');

            return response()->json([
                'data' => [
                    'event_id'        => $eventId,
                    'prs_activity_id' => $prsActivityId,
                    'revision'        => $revision,
                    'outcome'         => $mapped['outcome'],
                    'contract_id'     => $existingByEvent->contract_id,
                    'duplicate'       => true,
                ],
            ], 200);
        }

        $existingByRevision = DB::table('prs_closing_events')
            ->where('prs_activity_id', $prsActivityId)
            ->where('revision', $revision)
            ->first();

        if ($existingByRevision) {
            abort_unless(
                $existingByRevision->outcome === $mapped['outcome'],
                409,
                'The event ID or activity revision is already in use.'
            );

            return response()->json([
                'data' => [
                    'event_id'        => $existingByRevision->event_id,
                    'prs_activity_id' => $prsActivityId,
                    'revision'        => $revision,
                    'outcome'         => $mapped['outcome'],
                    'contract_id'     => $existingByRevision->contract_id,
                    'duplicate'       => true,
                ],
            ], 200);
        }

        $highestRevision = (int) (DB::table('prs_closing_events')
            ->where('prs_activity_id', $prsActivityId)
            ->max('revision') ?? 0);
        $isLatestRevision = $revision > $highestRevision;

        $contract = $this->resolveContract($validated, $prsActivityId);
        $createdNewContract = false;

        if ($isLatestRevision) {
            if ($contract !== null) {
                $this->applyMappedStatusToContract($contract, $mapped, $validated, $auditLog);
            } elseif (!empty($validated['institution']['institution_name'])) {
                $contract = $this->createContractFromHandoff($prsActivityId, $mapped, $validated, $productSync, $auditLog);
                $createdNewContract = true;
            }
        }

        DB::table('prs_closing_events')->insert([
            'event_id'               => $eventId,
            'prs_activity_id'        => $prsActivityId,
            'revision'               => $revision,
            'outcome'                => $mapped['outcome'],
            'prs_status'             => $mapped['prs_status'],
            'mapped_approval_status' => $mapped['approval_status'],
            'mapped_workflow_status' => $mapped['workflow_status'],
            'contract_id'            => $contract?->contract_id,
            'payload'                => json_encode($validated),
            'processed_at'           => now(),
            'created_at'             => now(),
        ]);

        if ($isLatestRevision) {
            $effectiveStatus = $createdNewContract
                ? 'Draft'
                : ($contract?->approvalStatus?->status_name ?? $mapped['approval_status']);
            $this->dispatchNotifications($contract, $prsActivityId, [...$mapped, 'approval_status' => $effectiveStatus], $createdNewContract, $notifications);
            if ($contract !== null) {
                try {
                    SyncContractToMeilisearch::dispatch((int) $contract->getKey());
                } catch (Throwable $e) {
                    Log::warning('Meilisearch sync skipped after PRS status update: ' . $e->getMessage());
                }
            }
        }

        $contract?->load(['approvalStatus', 'workflowStatus']);
        $responseApproval = $contract?->approvalStatus?->status_name ?? $mapped['approval_status'];
        $responseWorkflow = $contract?->workflowStatus?->status_name ?? ($createdNewContract ? null : $mapped['workflow_status']);

        return response()->json([
            'data' => [
                'event_id'               => $eventId,
                'prs_activity_id'        => $prsActivityId,
                'revision'               => $revision,
                'outcome'                => $mapped['outcome'],
                'mapped_approval_status' => $responseApproval,
                'mapped_workflow_status' => $responseWorkflow,
                'contract_id'            => $contract?->contract_id,
                'contract_code'          => $contract?->contract_code,
                'created_contract'       => $createdNewContract,
            ],
        ], 201);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function resolveContract(array $validated, int $prsActivityId): ?Contract
    {
        if (!empty($validated['contract_id'])) {
            $found = Contract::find((int) $validated['contract_id']);
            if ($found) {
                return $found;
            }
        }
        if (!empty($validated['contract_code'])) {
            $found = Contract::where('contract_code', $validated['contract_code'])->first();
            if ($found) {
                return $found;
            }
        }

        return Contract::where('prs_activity_id', $prsActivityId)->first();
    }

    /**
     * @param array{prs_status: string, outcome: string, approval_status: string, workflow_status: ?string} $mapped
     * @param array<string, mixed> $validated
     */
    private function applyMappedStatusToContract(
        Contract $contract,
        array $mapped,
        array $validated,
        AuditLogService $auditLog
    ): void {
        $oldData = $contract->toArray();
        $isDraft = $contract->approvalStatus?->status_name === 'Draft';
        $updates = [
            'prs_activity_id' => $contract->prs_activity_id ?? (int) ($validated['prs_activity_id'] ?? $validated['activity_id']),
        ];

        if (!$isDraft) {
            $approvalStatus = ContractApprovalStatus::firstOrCreate(['status_name' => $mapped['approval_status']]);
            $updates['approval_status_id'] = $approvalStatus->approval_status_id;

            if ($mapped['workflow_status'] !== null) {
                $workflowStatusId = ContractStatus::where('status_name', $mapped['workflow_status'])->value('status_id');
                if ($workflowStatusId) {
                    $updates['workflow_status_id'] = $workflowStatusId;
                }
            } else {
                $updates['workflow_status_id'] = null;
            }

            if ($mapped['approval_status'] === 'Approved') {
                $updates['rejection_reason'] = null;
                $updates['internal_approved_at'] = $contract->internal_approved_at ?? now();
            } elseif ($mapped['approval_status'] === 'Rejected') {
                $updates['rejection_reason'] = $validated['rejection_reason'] ?? $validated['remarks'] ?? 'Marked as Loss/Rejected by PRS.';
                $updates['notify_manager_count'] = 0;
            }
        }

        $contract->update($updates);

        // Intentionally do NOT dispatch NotifyPrsOfContractUpdateJob here:
        // this update originated from PRS (loop guard).
        $auditLog->log(
            'prs_status_synced',
            'Contract',
            (int) $contract->contract_id,
            null,
            $oldData,
            $contract->fresh()->toArray(),
            'PRS Integration'
        );
    }

    private function resolveExistingCategory(?string $requestedName): ContractCategory
    {
        $clean = trim((string) ($requestedName ?? ''));
        if ($clean !== '') {
            $matched = ContractCategory::where('is_active', true)
                ->whereRaw('LOWER(category_name) = ?', [strtolower($clean)])
                ->first();
            if ($matched) {
                return $matched;
            }
        }

        return ContractCategory::where('category_name', 'Equipment Lease')->first()
            ?? ContractCategory::where('is_active', true)->orderBy('category_id')->first()
            ?? ContractCategory::firstOrCreate(['category_name' => 'Equipment Lease']);
    }

    /**
     * @param array{prs_status: string, outcome: string, approval_status: string, workflow_status: ?string} $mapped
     * @param array<string, mixed> $validated
     */
    private function createContractFromHandoff(
        int $prsActivityId,
        array $mapped,
        array $validated,
        SyncPrsProductAction $productSync,
        AuditLogService $auditLog
    ): Contract {
        $category = $this->resolveExistingCategory($validated['category'] ?? null);
        $draftStatus = ContractApprovalStatus::firstOrCreate(['status_name' => 'Draft']);

        $regionName = $validated['region'] ?? 'Luzon';
        $regionId = DB::table('contract_regions')->where('region_name', $regionName)->value('region_id')
            ?? DB::table('contract_regions')->value('region_id');

        $productInput = $validated['product'] ?? [];
        $product = null;
        if (!empty($productInput['product_name'])) {
            $product = $productSync->upsert($productInput);
        }

        $serialNumber = $productInput['serial_number'] ?? $product?->serial_number ?? ('PRS-ACT-' . $prsActivityId);
        if (Contract::where('serial_number', $serialNumber)->exists()) {
            $serialNumber .= '-' . strtoupper(substr(md5((string) microtime(true)), 0, 4));
        }

        $sbuNumber = $product?->sbu_number
            ?? Product::formatSbuNumber($productInput['sbu_number'] ?? $productInput['sbu'] ?? 1);

        $contract = Contract::create([
            'category_id'        => $category->category_id,
            'approval_status_id' => $draftStatus->approval_status_id,
            'workflow_status_id' => null,
            'region_id'          => $regionId,
            'execution_required' => true,
            'prs_activity_id'    => $prsActivityId,
            'source_system'      => 'PRS',
            'source_reference_id' => (string) $prsActivityId,
            'product_id'         => $product?->id,
            'product_snapshot'   => $product?->toPrsProductPayload(),
            'prs_institution_id' => $validated['institution']['prs_institution_id'] ?? null,
            'prs_branch_id'      => $validated['institution']['prs_branch_id'] ?? null,
            'bp_name'            => $validated['institution']['institution_name'],
            'item_code'          => $product?->item_code ?? ($productInput['product_code'] ?? 'ITM-PRS'),
            'sbu_number'         => $sbuNumber,
            'serial_number'      => $serialNumber,
            'description'        => $productInput['description'] ?? $product?->description ?? "PRS Closing Activity #{$prsActivityId}",
            'equipment_name'     => $product?->product_name ?? ($productInput['product_name'] ?? null),
            'brand'              => $product?->brand ?? $product?->manufacturer ?? ($productInput['brand'] ?? null),
            'model'              => $product?->model ?? ($productInput['model'] ?? null),
            'sbsi_branch'        => $validated['sbsi_branch'] ?? 'Main Office',
            'start_date'         => $validated['start_date'] ?? now()->toDateString(),
            'end_date'           => $validated['end_date'] ?? now()->addYear()->toDateString(),
        ]);

        $auditLog->log(
            'created_from_prs',
            'Contract',
            (int) $contract->contract_id,
            null,
            [],
            $contract->toArray(),
            'PRS Integration'
        );

        return $contract;
    }

    /**
     * @param array{prs_status: string, outcome: string, approval_status: string, workflow_status: ?string} $mapped
     */
    private function dispatchNotifications(
        ?Contract $contract,
        int $prsActivityId,
        array $mapped,
        bool $createdNewContract,
        NotificationService $notifications
    ): void {
        try {
            $label = $contract?->contract_code ?? "PRS Activity #{$prsActivityId}";
            $message = $createdNewContract
                ? "PRS Closing Activity #{$prsActivityId} created draft contract {$label} for review."
                : "PRS updated {$label} status to {$mapped['approval_status']} ({$mapped['prs_status']}).";

            $notifications->push(
                $contract?->contract_id ? (int) $contract->contract_id : null,
                $createdNewContract ? 'prs_draft_created' : 'prs_status_updated',
                $message,
                $createdNewContract ? 'Sales,Employee,Manager,Admin' : 'Manager,Admin',
                $contract?->created_by ? (int) $contract->created_by : null
            );
        } catch (Throwable $e) {
            Log::warning('Failed to push PRS status sync notification: ' . $e->getMessage());
        }
    }
}
