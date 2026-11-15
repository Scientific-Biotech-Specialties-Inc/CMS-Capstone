<?php

namespace App\Http\Controllers;

use App\Models\ApprovalTask;
use App\Models\Contract;
use App\Models\Workflow;
use App\Services\ApprovalEngineService;
use App\Services\AuthService;
use App\Services\RoleProxyService;
use Illuminate\Http\Request;

/**
 * Dynamic Approval Workflow Engine runtime endpoints (Phase 5). Entirely
 * additive to the existing contract approve/reject flow in
 * ContractController — these endpoints only ever return data for
 * contracts whose category has (or had) an active workflow when the
 * contract was created.
 */
class ApprovalEngineController extends Controller
{
    public function __construct(
        protected ApprovalEngineService $engine,
        protected RoleProxyService $roleProxy,
        protected AuthService $authService,
    ) {
    }

    /**
     * GET /contracts/{id}/approval-progress — the latest run's steps and
     * task statuses for a contract being routed through the engine.
     * Returns an empty payload (engine: false) for contracts on the
     * legacy flow, rather than a 404, so the frontend can render
     * accordingly either way.
     */
    public function progress(Request $request, $id)
    {
        $contract = Contract::with(['approvalStatus', 'workflowStatus'])
            ->where('contract_code', $id)->orWhere('contract_id', $id)->firstOrFail();

        $role = $request->get('auth_role');
        $permissions = (array) $request->get('auth_permissions', []);
        if (
            in_array($role, ['Sales', 'Employee'], true)
            && !in_array('cms.contracts.approve', $permissions, true)
            && $contract->created_by !== null
            && (int) $contract->created_by !== (int) $request->get('auth_id')
        ) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Creator display for the tracker's synthetic "Contract created"
        // node. Resolved in batch style.
        $creatorName = null;
        if ($contract->created_by) {
            foreach ($this->authService->getUsersBatch([(int) $contract->created_by]) as $u) {
                $full = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                $creatorName = $full !== '' ? $full : ($u['email'] ?? null);
            }
        }

        $instances = \App\Models\ApprovalInstance::with(['tasks', 'workflow.steps.workflowStepRoles'])
            ->where('contract_id', $contract->contract_id)
            ->orderBy('run_number')
            ->get();

        if ($instances->isEmpty()) {
            return response()->json([
                'data' => [
                    'engine' => false,
                    'contract_id' => $contract->contract_code ?? (string) $contract->contract_id,
                    'contract_created_at' => $contract->created_at?->toIso8601String(),
                    'contract_created_by' => $creatorName,
                    'my_tasks' => [],
                    'current_step_roles' => $contract->approvalStatus?->status_name === 'Pending' ? ['Manager'] : [],
                    'runs' => [],
                    'legacy_run' => $this->synthesizeLegacyRun($contract, $creatorName),
                    'execution_stages' => $this->buildExecutionStages($contract, null),
                ],
            ]);
        }

        // Phase 6 live-data wiring: enrich every task once per response so
        // the Visual Tracker can render deleted/renamed-role badges and
        // delegation names without fan-out calls from the browser (which
        // must never hold the X-Internal-Secret). Batched per distinct
        // role/user, degrading to snapshot-only data if auth-module is
        // unreachable (describeRole()/getUsersBatch() already fall back to
        // empty on connection error — never fail the whole response).
        $roleIds = $instances->flatMap(fn ($i) => $i->tasks->pluck('auth_role_id'))
            ->unique()->values()->all();
        $roleMeta = [];
        foreach ($roleIds as $roleId) {
            $roleMeta[$roleId] = $this->roleProxy->describeRole((int) $roleId);
        }

        $delegateIds = $instances->flatMap(fn ($i) => $i->tasks)
            ->filter(fn ($t) => $t->acted_via_delegation && $t->acted_by_user_id)
            ->map(fn ($t) => (int) $t->acted_by_user_id)
            ->unique()->values()->all();
        $userNames = [];
        if (!empty($delegateIds)) {
            foreach ($this->authService->getUsersBatch($delegateIds) as $u) {
                $uid = (int) ($u['id'] ?? 0);
                if ($uid) {
                    $full = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                    $userNames[$uid] = $full !== '' ? $full : ($u['email'] ?? "User #{$uid}");
                }
            }
        }

        $latest = $instances->last();
        $actor = (int) $request->get('auth_id');
        $mayAct = $latest->isInProgress() && (!config('services.features.enforce_sod') || (int) $contract->created_by !== $actor);
        $myTasks = $mayAct ? $latest->tasks
            ->filter(fn (ApprovalTask $task) => (int) $task->step_id === (int) $latest->current_step_id && $this->engine->canActOn($task, $actor))
            ->map(fn (ApprovalTask $task) => ['id' => $task->id, 'role_name' => $roleMeta[$task->auth_role_id]['current_name'] ?? $task->auth_role_id])
            ->values() : [];

        return response()->json([
            'data' => [
                'engine' => true,
                'contract_id' => $contract->contract_code ?? (string) $contract->contract_id,
                'contract_created_at' => $contract->created_at?->toIso8601String(),
                'contract_created_by' => $creatorName,
                'my_tasks' => $myTasks,
                // Phase 7: the roles owning the latest run's current step,
                // so the frontend can label the nudge button without
                // deriving it from tracker nodes. [] when no run is active.
                'current_step_roles' => $this->resolveCurrentStepRoleNames($instances->last(), $roleMeta),
                'runs' => $instances->map(fn ($instance) => $this->formatRun($instance, $roleMeta, $userNames))->values(),
                'execution_stages' => $this->buildExecutionStages($contract, $instances->last()?->workflow),
            ],
        ]);
    }

    /**
     * POST /contracts/{id}/approval-tasks/{taskId}/decision
     * body: { decision: 'approved'|'rejected', comment?: string }
     *
     * The acting user must currently hold the task's assigned role
     * (checked live against auth-module) — enforced in
     * ApprovalEngineService::decide(), not here.
     */
    public function decide(Request $request, $id, $taskId)
    {
        $request->validate([
            'decision' => 'required|string|in:approved,rejected',
            'comment'  => 'nullable|string',
        ]);

        $contract = Contract::where('contract_code', $id)->orWhere('contract_id', $id)->firstOrFail();

        $task = ApprovalTask::whereHas('instance', function ($q) use ($contract) {
            $q->where('contract_id', $contract->contract_id);
        })->findOrFail($taskId);

        $actingUserId = (int) $request->get('auth_id');

        // Separation of Duties: a contract creator cannot approve or reject
        // their own contract when enforce_sod is active.
        if (
            config('services.features.enforce_sod')
            && $contract->created_by !== null
            && (int) $contract->created_by === $actingUserId
        ) {
            return response()->json([
                'message' => 'You cannot approve or reject a contract you created.',
            ], 403);
        }

        $task = $this->engine->decide($task, $actingUserId, $request->input('decision'), $request->input('comment'));

        return response()->json(['data' => $this->formatTask($task)]);
    }

    /**
     * Phase 7 (decision #13): role names owning the latest run's current
     * step, for the frontend nudge-button label. Prefers the LIVE current
     * name from the already-fetched describe data (a renamed role's stale
     * snapshot would target nobody — the notification service matches
     * target_roles by exact current role name), falling back to the
     * snapshot. Reads the eager-loaded step collection; no extra query.
     */
    private function resolveCurrentStepRoleNames($instance, array $roleMeta): array
    {
        if (!$instance || !in_array($instance->status, ['in_progress', 'revisions_required'], true)) {
            return [];
        }
        if (!$instance->current_step_id) {
            return [];
        }
        $step = $instance->workflow->steps->firstWhere('id', (int) $instance->current_step_id);
        if (!$step) {
            return [];
        }

        return $step->workflowStepRoles
            ->map(fn ($sr) => $roleMeta[$sr->auth_role_id]['current_name'] ?? $sr->role_name_snapshot)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function formatRun($instance, array $roleMeta = [], array $userNames = []): array
    {
        $steps = $instance->workflow->steps->sortBy('order_index')->values();
        $tasksByStep = $instance->tasks->groupBy('step_id');

        return [
            'run_number' => $instance->run_number,
            'status'     => $instance->status,
            'current_step_id' => $instance->current_step_id,
            'draft_document_id' => $instance->draft_document_id,
            'groups'     => $steps->map(function ($step) use ($tasksByStep, $instance, $roleMeta, $userNames) {
                $tasks = $tasksByStep->get($step->id, collect());
                return [
                    'step_id' => $step->id,
                    'mode'    => $step->mode,
                    // Step definitions (not just materialized tasks) so the
                    // tracker can render not-yet-reached future steps as
                    // pending/locked. Already eager-loaded — no extra query.
                    'roles'   => $step->workflowStepRoles->map(function ($sr) use ($roleMeta) {
                        [$deleted, $renamedFrom] = $this->roleDisplay((int) $sr->auth_role_id, $roleMeta);
                        return [
                            'auth_role_id' => $sr->auth_role_id,
                            'role_name' => $sr->role_name_snapshot,
                            'role_deleted' => $deleted,
                            'role_renamed_from' => $renamedFrom,
                        ];
                    })->values(),
                    'tasks'   => $tasks->map(fn ($t) => $this->formatTask($t, $roleMeta, $userNames, $instance))->values(),
                ];
            })->values(),
        ];
    }

    /**
     * Decision #14 display state for one role: [deleted, renamedFrom].
     * Deleted takes precedence; describe failures degrade to
     * snapshot-only (not deleted, no rename).
     */
    private function roleDisplay(int $authRoleId, array $roleMeta): array
    {
        $describe = $roleMeta[$authRoleId] ?? null;
        $deleted = (bool) ($describe['deleted'] ?? false);
        $renamedFrom = null;
        if (!$deleted && ($describe['was_renamed'] ?? false)) {
            $history = $describe['name_history'] ?? [];
            $last = end($history);
            $renamedFrom = is_array($last)
                ? ($last['old_name'] ?? $last['name'] ?? null)
                : (is_string($last) ? $last : null);
        }
        return [$deleted, $renamedFrom];
    }

    private function formatTask(ApprovalTask $task, array $roleMeta = [], array $userNames = [], $instance = null): array
    {
        $stepRole = \App\Models\WorkflowStepRole::where('step_id', $task->step_id)
            ->where('auth_role_id', $task->auth_role_id)
            ->first();

        // Decision #14 display state, resolved server-side (Phase 6).
        // describeRole() shape (Phase 1): exists/deleted/current_name/
        // was_renamed/name_history. Deleted takes precedence over renamed.
        [$deleted, $renamedFrom] = $this->roleDisplay((int) $task->auth_role_id, $roleMeta);
        if ($stepRole === null && empty($roleMeta)) {
            $deleted = true;
        }

        $delegateName = null;
        if ($task->acted_via_delegation && $task->acted_by_user_id) {
            $delegateName = $userNames[(int) $task->acted_by_user_id] ?? null;
        }

        // Explicit currency flag so the frontend never has to guess which
        // pending task is "Now" (Phase 6 decision #3).
        $isCurrent = $instance
            && in_array($instance->status, ['in_progress', 'revisions_required'], true)
            && $instance->current_step_id !== null
            && (int) $task->step_id === (int) $instance->current_step_id
            && in_array($task->status, ['pending', 'on_hold'], true);

        return [
            'id'                   => $task->id,
            'step_id'              => $task->step_id,
            'auth_role_id'         => $task->auth_role_id,
            'role_name'            => $stepRole?->role_name_snapshot,
            'role_deleted'         => $deleted,
            'role_renamed_from'    => $renamedFrom,
            'delegate_name'        => $delegateName,
            'is_current'           => (bool) $isCurrent,
            'status'               => $task->status,
            'acted_by_user_id'     => $task->acted_by_user_id,
            'acted_via_delegation' => $task->acted_via_delegation,
            'acted_at'             => $task->acted_at?->toIso8601String(),
            'comment'              => $task->comment,
        ];
    }

    private function synthesizeLegacyRun(Contract $contract, ?string $creatorName): array
    {
        $statusName = $contract->approvalStatus?->status_name ?? 'Pending';
        $nodeStatus = match ($statusName) {
            'Approved' => 'completed',
            'Rejected' => 'rejected',
            default => 'active',
        };

        return [
            'run_number' => 1,
            'status' => $nodeStatus === 'completed' ? 'approved' : ($nodeStatus === 'rejected' ? 'revisions_required' : 'in_progress'),
            'current_step_id' => 1,
            'draft_document_id' => $contract->execution_draft_id,
            'groups' => [
                [
                    'step_id' => 1,
                    'mode' => 'sequential',
                    'roles' => [
                        [
                            'auth_role_id' => 0,
                            'role_name' => 'Manager',
                            'role_deleted' => false,
                            'role_renamed_from' => null,
                        ],
                    ],
                    'tasks' => [
                        [
                            'id' => 1,
                            'step_id' => 1,
                            'auth_role_id' => 0,
                            'role_name' => 'Manager',
                            'role_deleted' => false,
                            'role_renamed_from' => null,
                            'delegate_name' => null,
                            'is_current' => $nodeStatus === 'active',
                            'status' => $nodeStatus,
                            'acted_by_user_id' => null,
                            'acted_via_delegation' => false,
                            'acted_at' => $nodeStatus !== 'active' ? ($contract->internal_approved_at?->toIso8601String() ?? $contract->updated_at?->toIso8601String()) : null,
                            'comment' => $contract->rejection_reason,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildExecutionStages(Contract $contract, ?Workflow $workflow): array
    {
        $stages = ['SBSI Review', 'Client Review', 'For Signing', 'Signed', 'Notarized PDF'];
        $isApproved = $contract->approvalStatus?->status_name === 'Approved';
        $currentWorkflowStage = $contract->workflowStatus?->status_name;
        $events = $contract->executionEvents()->orderBy('id')->get();
        $pendingEvent = $events->where('state', 'pending')->first();
        $latestEventByStage = $events->groupBy('stage')->map(fn ($evts) => $evts->last());
        $configuredReviewers = $workflow?->execution_reviewers ?? [];

        $currIdx = -1;
        if ($isApproved) {
            $found = array_search($currentWorkflowStage, $stages, true);
            $currIdx = $found !== false ? $found : 0;
            if ($currentWorkflowStage === 'Notarized PDF' || $contract->notarized_document_id) {
                $currIdx = 4;
            }
        }

        $result = [];
        foreach ($stages as $i => $stageName) {
            $revConfig = $configuredReviewers[$stageName] ?? [];
            $reviewerNames = !empty($revConfig)
                ? array_column($revConfig, 'role_name')
                : ($stageName === 'For Signing' ? ['Manager', 'CEO'] : ['Manager']);

            $latestEvt = $latestEventByStage->get($stageName);
            $actedAt = $latestEvt?->decided_at?->toIso8601String() ?? $latestEvt?->created_at?->toIso8601String();

            if (!$isApproved) {
                $status = 'pending';
                $subText = 'Execution starts after approval';
            } elseif ($currentWorkflowStage === 'Notarized PDF' || ($i < $currIdx) || ($i === 4 && $contract->notarized_document_id)) {
                $status = 'completed';
                $subText = $latestEvt?->reason ? 'Confirmed · ' . $latestEvt->reason : 'Confirmed';
            } elseif ($i === $currIdx) {
                if ($contract->revision_paused_at) {
                    $status = 'on_hold';
                    $subText = 'Revision requested';
                } elseif ($pendingEvent && $pendingEvent->stage === $stageName) {
                    $status = 'active';
                    $subText = 'Submitted · Awaiting confirmation';
                } elseif ($latestEvt && $latestEvt->state === 'returned') {
                    $status = 'on_hold';
                    $subText = 'Returned' . ($latestEvt->reason ? ' · ' . $latestEvt->reason : '');
                } else {
                    $status = 'active';
                    $subText = $stageName === 'For Signing' ? 'Awaiting signed document' : 'In progress';
                }
            } else {
                $status = 'pending';
                $subText = $stageName === 'Notarized PDF' ? 'Optional post-signing step' : 'Pending';
            }

            $result[] = [
                'stage' => $stageName,
                'status' => $status,
                'is_current' => $isApproved && ($i === $currIdx) && ($currentWorkflowStage !== 'Notarized PDF'),
                'acted_at' => $actedAt,
                'reviewer_roles' => $reviewerNames,
                'sub_text' => $subText,
                'document_id' => $latestEvt?->document_id,
            ];
        }

        return $result;
    }
}
