<?php

namespace App\Services;

use App\Models\ContractCategory;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepRole;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Workflow Builder business logic (Phase 4 of the dynamic approval
 * workflow engine plan). CRUD + validation only — the runtime approval
 * engine (routing contracts through a workflow) is Phase 5.
 */
class WorkflowService
{
    public function __construct(protected RoleProxyService $roleProxy)
    {
    }

    /**
     * All contract types (categories) with their current workflow status,
     * for the Workflow Builder's landing list.
     */
    public function listContractTypesWithWorkflows(): array
    {
        $categories = ContractCategory::with(['workflows' => function ($q) {
            $q->whereNull('superseded_at')->orderByDesc('updated_at')->orderByDesc('id');
        }])->orderBy('category_name')->get();

        return $categories->map(function (ContractCategory $cat) {
            $active = $cat->workflows->firstWhere('status', 'active');
            $draft = $cat->workflows->firstWhere('status', 'draft');

            return [
                'contract_type_id'   => $cat->category_id,
                'contract_type_name' => $cat->category_name,
                'is_active'          => (bool) $cat->is_active,
                'active_workflow'    => $active ? $this->formatWorkflowSummary($active) : null,
                'draft_workflow'     => $draft ? $this->formatWorkflowSummary($draft) : null,
            ];
        })->values()->all();
    }

    public function getWorkflow(int $workflowId): Workflow
    {
        $workflow = Workflow::with(['steps.workflowStepRoles'])->find($workflowId);
        if (!$workflow) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Workflow::class, $workflowId);
        }
        return $workflow;
    }

    /**
     * Create or update a workflow (and fully replace its steps/roles) in a
     * single transaction. Drafts can be saved with zero or incomplete
     * steps (decision #11) — they are never used for routing. Activating
     * (status = 'active') requires at least one step, and triggers the
     * save-time permission validation (decision #6).
     *
     * $payload shape:
     *   contract_type_id, name, status ('draft'|'active'), resubmit_mode,
     *   steps: [{ order_index, mode, roles: [{ auth_role_id, role_name }] }]
     */
    public function saveWorkflow(array $payload, ?int $workflowId, int $actorUserId, string $token, ?string $sessionId): Workflow
    {
        $status = $payload['status'] ?? 'draft';

        if ($status === 'active') {
            $this->validateForActivation($payload, $token, $sessionId);
        }

        return DB::transaction(function () use ($payload, $workflowId, $actorUserId, $status) {
            $categoryId = (int) $payload['contract_type_id'];
            ContractCategory::lockForUpdate()->findOrFail($categoryId);

            $replacesId = isset($payload['replace_active_workflow_id']) ? (int) $payload['replace_active_workflow_id'] : null;
            if ($status === 'active') {
                if ($replacesId !== null) {
                    abort_if($replacesId === $workflowId, 422, 'Activate a separate version to replace the current workflow.');
                    $replaced = Workflow::where('contract_type_id', $categoryId)->where('status', 'active')
                        ->lockForUpdate()->find($replacesId);
                    abort_unless($replaced, 409, 'The active workflow changed. Reload Approval Workflows and review the current version.');
                    $replaced->forceFill(['status' => 'draft', 'superseded_at' => now()])->save();
                }
                $this->assertSingleActiveWorkflowPerType($categoryId, $workflowId);
            }

            if ($workflowId) {
                $workflow = Workflow::where('contract_type_id', $categoryId)->lockForUpdate()->findOrFail($workflowId);
                abort_if($workflow->superseded_at !== null, 409, 'This version was replaced. Reload Approval Workflows to open the current version.');
                $reviewers = $payload['execution_reviewers'] ?? $workflow->execution_reviewers;
                $resubmitMode = $payload['resubmit_mode'] ?? $workflow->resubmit_mode;
                if (($reviewers ?: []) != ($workflow->execution_reviewers ?: []) || $resubmitMode !== $workflow->resubmit_mode) {
                    $this->assertStepsEditable($workflow);
                }
                $workflow->update([
                    'contract_type_id' => $payload['contract_type_id'],
                    'name'             => $payload['name'],
                    'status'           => $status,
                    'resubmit_mode'    => $resubmitMode,
                    'execution_reviewers' => $reviewers,
                ]);
                $newSteps = $payload['steps'] ?? [];
                if ($this->stepSignature($workflow) === $this->payloadSignature($newSteps)) {
                    // Metadata-only save (name/mode flags unchanged in
                    // substance): keep the existing step rows so live and
                    // historical approval-task references stay intact.
                    return $workflow->load(['steps.workflowStepRoles']);
                }
                // Full replace of steps/roles — simplest correct model for
                // a builder UI that always submits the complete step list.
                // Guarded: replacing steps cascade-deletes tasks and
                // orphans live runs (tasks gone, current_step NULL).
                $this->assertStepsEditable($workflow);
                $workflow->steps()->delete(); // cascades to workflow_step_roles
            } else {
                if (isset($payload['replace_draft_workflow_id'])) {
                    $draft = Workflow::where('contract_type_id', $categoryId)->where('status', 'draft')
                        ->whereNull('superseded_at')->lockForUpdate()->find($payload['replace_draft_workflow_id']);
                    abort_unless($draft, 409, 'The draft changed. Reload Approval Workflows to open the current draft.');
                    $draft->forceFill(['superseded_at' => now()])->save();
                }
                $workflow = Workflow::create([
                    'contract_type_id' => $payload['contract_type_id'],
                    'name'             => $payload['name'],
                    'status'           => $status,
                    'resubmit_mode'    => $payload['resubmit_mode'] ?? 'restart',
                    'execution_reviewers' => $payload['execution_reviewers'] ?? null,
                    'created_by'       => $actorUserId,
                ]);
            }

            foreach ($payload['steps'] ?? [] as $stepData) {
                $step = WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'order_index' => $stepData['order_index'],
                    'mode'        => $stepData['mode'],
                ]);

                foreach ($stepData['roles'] ?? [] as $roleData) {
                    WorkflowStepRole::create([
                        'step_id'            => $step->id,
                        'auth_role_id'       => $roleData['auth_role_id'],
                        'role_name_snapshot' => $roleData['role_name'],
                    ]);
                }
            }

            return $workflow->load(['steps.workflowStepRoles']);
        });
    }

    /**
     * Blocks step replacement on a workflow that already has approval
     * runs: deleting steps cascade-deletes their tasks (live AND history)
     * and nulls instances' current_step, orphaning in-progress runs with
     * an empty tracker. Mirrors deleteWorkflow()'s 409 philosophy —
     * duplicate the workflow to evolve the chain instead.
     */
    private function assertStepsEditable(Workflow $workflow): void
    {
        if ($workflow->instances()->exists()) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'This version is assigned to existing contracts. Reopen the editor to save your changes as a new version.',
                ], 409)
            );
        }
    }

    /**
     * Canonical signature of a workflow's persisted steps for
     * metadata-only-save detection: ordered modes + sorted role pairs.
     */
    private function stepSignature(Workflow $workflow): array
    {
        $workflow->loadMissing(['steps.workflowStepRoles']);

        return $workflow->steps->sortBy('order_index')->values()->map(fn ($step) => [
            'mode' => $step->mode,
            'roles' => $step->workflowStepRoles
                ->map(fn ($r) => [(int) $r->auth_role_id, (string) $r->role_name_snapshot])
                ->sort()
                ->values()
                ->all(),
        ])->all();
    }

    /**
     * Same canonical shape for the builder-submitted payload.
     */
    private function payloadSignature(array $steps): array
    {
        $norm = array_map(fn ($stepData) => [
            'order' => $stepData['order_index'] ?? 0,
            'mode' => $stepData['mode'] ?? 'sequential',
            'roles' => collect($stepData['roles'] ?? [])
                ->map(fn ($roleData) => [(int) ($roleData['auth_role_id'] ?? 0), (string) ($roleData['role_name'] ?? '')])
                ->sort()
                ->values()
                ->all(),
        ], $steps);

        usort($norm, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_map(fn ($entry) => ['mode' => $entry['mode'], 'roles' => $entry['roles']], $norm);
    }

    /**
     * One-time duplication (decision #10): copies all steps/roles from an
     * existing workflow onto a NEW workflow row for a different contract
     * type, as a draft. No ongoing link is kept to the source — editing
     * either afterward never affects the other.
     */
    public function duplicateWorkflow(int $sourceWorkflowId, int $targetContractTypeId, string $newName, int $actorUserId): Workflow
    {
        $source = Workflow::with(['steps.workflowStepRoles'])->findOrFail($sourceWorkflowId);

        return DB::transaction(function () use ($source, $targetContractTypeId, $newName, $actorUserId) {
            $copy = Workflow::create([
                'contract_type_id' => $targetContractTypeId,
                'name'             => $newName,
                'status'           => 'draft',
                'resubmit_mode'    => $source->resubmit_mode,
                'execution_reviewers' => $source->execution_reviewers,
                'created_by'       => $actorUserId,
            ]);

            foreach ($source->steps as $step) {
                $newStep = WorkflowStep::create([
                    'workflow_id' => $copy->id,
                    'order_index' => $step->order_index,
                    'mode'        => $step->mode,
                ]);

                foreach ($step->workflowStepRoles as $role) {
                    WorkflowStepRole::create([
                        'step_id'            => $newStep->id,
                        'auth_role_id'       => $role->auth_role_id,
                        'role_name_snapshot' => $role->role_name_snapshot,
                    ]);
                }
            }

            return $copy->load(['steps.workflowStepRoles']);
        });
    }

    public function deleteWorkflow(int $workflowId): void
    {
        $workflow = Workflow::findOrFail($workflowId);

        if ($workflow->instances()->exists()) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'This workflow has contracts that have gone through it and cannot be deleted. Deactivate it instead by leaving the contract type without an active workflow.',
                ], 409)
            );
        }

        $workflow->delete();
    }

    /**
     * Decision #6: every role assigned to any step of a workflow being
     * ACTIVATED must hold cms.contracts.view AND cms.contracts.edit.
     * Activation is blocked (not saved) if not, naming the offending
     * step/role so the admin can fix it.
     */
    private function validateForActivation(array $payload, string $token, ?string $sessionId): void
    {
        $steps = $payload['steps'] ?? [];

        if (empty($steps)) {
            throw ValidationException::withMessages([
                'steps' => ['An active workflow must have at least one step.'],
            ]);
        }

        foreach ($steps as $stepIndex => $stepData) {
            $roles = $stepData['roles'] ?? [];

            if (empty($roles)) {
                throw ValidationException::withMessages([
                    "steps.{$stepIndex}" => ['Step ' . ($stepData['order_index'] ?? $stepIndex + 1) . ' has no role assigned.'],
                ]);
            }

            foreach ($roles as $roleData) {
                $roleId = $roleData['auth_role_id'];
                $roleName = $roleData['role_name'] ?? "role #{$roleId}";
                $slugs = $this->roleProxy->getRolePermissionSlugs($roleId, $token, $sessionId);

                $missing = array_diff(['cms.contracts.view', 'cms.contracts.edit'], $slugs);
                if (!empty($missing)) {
                    throw ValidationException::withMessages([
                        "steps.{$stepIndex}.roles" => [
                            "Role \"{$roleName}\" at step " . ($stepData['order_index'] ?? $stepIndex + 1) .
                            ' is missing required permission(s): ' . implode(', ', $missing) .
                            '. Grant these permissions to the role before it can be used in an active workflow.',
                        ],
                    ]);
                }
            }
        }

        foreach ($payload['execution_reviewers'] ?? [] as $stage => $reviewers) {
            foreach ($reviewers as $reviewer) {
                $role = $this->roleProxy->describeRole((int) $reviewer['auth_role_id']);
                if (!($role['exists'] ?? false) || ($role['deleted'] ?? false)
                    || ($stage === 'For Signing' && !in_array($role['current_name'] ?? '', ['Manager', 'CEO'], true))) {
                    throw ValidationException::withMessages(['execution_reviewers' => "Select an eligible reviewer for {$stage}."]);
                }
                if (!in_array('cms.contracts.view', $this->roleProxy->getRolePermissionSlugs((int) $reviewer['auth_role_id'], $token, $sessionId), true)) {
                    throw ValidationException::withMessages(['execution_reviewers' => "Reviewers for {$stage} need permission to view contracts."]);
                }
            }
        }
    }

    /**
     * Decision #9: only one ACTIVE workflow per contract type. Enforced
     * here (application layer) rather than a DB constraint, since the
     * check needs to exclude the workflow currently being saved (its own
     * previous 'active' row, if editing an already-active workflow).
     */
    private function assertSingleActiveWorkflowPerType(int $contractTypeId, ?int $excludingWorkflowId): void
    {
        $query = Workflow::where('contract_type_id', $contractTypeId)->where('status', 'active');
        if ($excludingWorkflowId) {
            $query->where('id', '!=', $excludingWorkflowId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'contract_type_id' => ['This contract type already has an active workflow. Deactivate it first before activating another.'],
            ]);
        }
    }

    private function formatWorkflowSummary(Workflow $workflow): array
    {
        return [
            'id'            => $workflow->id,
            'name'          => $workflow->name,
            'status'        => $workflow->status,
            'resubmit_mode' => $workflow->resubmit_mode,
            'step_count'    => $workflow->steps()->count(),
            'updated_at'    => $workflow->updated_at?->toIso8601String(),
        ];
    }
}
