<?php

namespace App\Services;

use App\Models\ApprovalInstance;
use App\Models\ApprovalTask;
use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dynamic Approval Workflow Engine runtime (Phase 5). Routes a contract
 * through its contract type's ACTIVE workflow, if one exists. Entirely
 * additive and gated by config('services.features.workflow_engine_enabled')
 * — with the flag off, or for a contract type with no active workflow,
 * nothing in this service is ever invoked and contracts behave exactly as
 * they did before this phase (the legacy single-manager-approval flow in
 * ContractController).
 */
class ApprovalEngineService
{
    public function __construct(
        protected RoleProxyService $roleProxy,
        protected NotificationService $notificationService,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.features.workflow_engine_enabled');
    }

    /**
     * The active workflow for a contract type, or null (legacy flow).
     */
    public function resolveActiveWorkflow(int $contractTypeId): ?Workflow
    {
        return Workflow::with('steps.workflowStepRoles')
            ->where('contract_type_id', $contractTypeId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Does this contract currently have an in-progress or revisions-required
     * engine instance? Used by ContractController to decide whether the
     * legacy updateStatus() endpoint must defer to the engine instead
     * (see guardLegacyStatusUpdate()).
     */
    public function hasActiveInstance(int $contractId): bool
    {
        return ApprovalInstance::where('contract_id', $contractId)
            ->awaitingOutcome()
            ->exists();
    }

    /**
     * Blocks the legacy manager approve/reject endpoint for a contract
     * that is being routed through the engine, pointing the caller at the
     * task-decision endpoint instead. Called from ContractController;
     * throwing here is deliberate — the controller performs no other work
     * once an engine instance exists for the contract.
     */
    public function guardLegacyStatusUpdate(int $contractId): void
    {
        if ($this->hasActiveInstance($contractId)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This contract is being routed through an approval workflow. Act on the pending approval task instead of the legacy status endpoint.',
            ], 409));
        }
    }

    /**
     * Starts run #1 of a contract's approval instance. Called from
     * ContractController::store() right after a contract is created, only
     * when isEnabled() and the contract's category has an active workflow.
     */
    public function startInstance(Contract $contract, Workflow $workflow): ApprovalInstance
    {
        return DB::transaction(function () use ($contract, $workflow) {
            $instance = ApprovalInstance::create([
                'contract_id' => $contract->contract_id,
                'workflow_id' => $workflow->id,
                'run_number'  => 1,
                'status'      => 'in_progress',
                'draft_document_id' => $contract->execution_draft_id,
            ]);

            $firstStep = $workflow->steps->sortBy('order_index')->first();
            if ($firstStep) {
                $this->enterStep($instance, $firstStep);
            }

            return $instance->refresh();
        });
    }

    /**
     * Resubmission after a rejection (spec 5.5 / decision #11): creates a
     * NEW ApprovalInstance (run_number incremented), leaving the rejected
     * run's tasks untouched as history. Starts at step 1 (resubmit_mode =
     * 'restart', the default) or at the step that rejected
     * ('resume_at_rejected').
     */
    public function resubmit(Contract $contract, bool $restartApproved = false): ?ApprovalInstance
    {
        $lastInstance = ApprovalInstance::where('contract_id', $contract->contract_id)
            ->orderByDesc('run_number')
            ->first();

        if (!$lastInstance || $lastInstance->status !== ($restartApproved ? 'approved' : 'revisions_required')) {
            return null;
        }

        $workflow = Workflow::with('steps.workflowStepRoles')->find($lastInstance->workflow_id);
        if (!$workflow) {
            return null;
        }

        return DB::transaction(function () use ($contract, $workflow, $lastInstance, $restartApproved) {
            $newInstance = ApprovalInstance::create([
                'contract_id' => $contract->contract_id,
                'workflow_id' => $workflow->id,
                'run_number'  => $lastInstance->run_number + 1,
                'status'      => 'in_progress',
                'draft_document_id' => $contract->execution_draft_id,
            ]);

            $steps = $workflow->steps->sortBy('order_index')->values();

            $startStep = !$restartApproved && $workflow->resubmit_mode === 'resume_at_rejected' && $lastInstance->current_step_id
                ? $steps->firstWhere('id', $lastInstance->current_step_id)
                : $steps->first();

            // If the rejected step was removed from the workflow since
            // (edited in the builder), fall back to restarting at step 1
            // rather than erroring the resubmission.
            $startStep = $startStep ?? $steps->first();

            if ($startStep) {
                $this->enterStep($newInstance, $startStep);
            }

            return $newInstance->refresh();
        });
    }

    /**
     * Record a decision (approve/reject) on one task. $actingUserId must
     * currently hold the task's role — checked live against auth-module
     * (so a delegation granted or revoked moments ago takes effect
     * immediately, per Option C) — or the decision is rejected with a 403.
     */
    public function decide(ApprovalTask $task, int $actingUserId, string $decision, ?string $comment): ApprovalTask
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new HttpResponseException(response()->json(['message' => 'Invalid decision.'], 422));
        }

        if (!$task->isPending()) {
            throw new HttpResponseException(response()->json(['message' => 'This task has already been actioned or is not awaiting action.'], 409));
        }

        $holderInfo = $this->findHolderInfo($task->auth_role_id, $actingUserId);
        if (!$holderInfo) {
            throw new HttpResponseException(response()->json([
                'message' => 'You do not currently hold the role required for this approval step.',
            ], 403));
        }

        return DB::transaction(function () use ($task, $actingUserId, $decision, $comment) {
            $instance = ApprovalInstance::lockForUpdate()->findOrFail($task->instance_id);
            $task = ApprovalTask::lockForUpdate()->findOrFail($task->getKey());
            abort_unless($instance->isInProgress() && $task->isPending(), 409, 'This approval task is no longer pending.');
            $task->update([
                'status'           => $decision,
                'acted_by_user_id' => $actingUserId,
                'acted_at'         => now(),
                'comment'          => $comment,
            ]);

            $instance = $task->instance()->with('workflow.steps.workflowStepRoles')->first();

            if ($decision === 'rejected') {
                $this->handleRejection($instance, $task);
            } else {
                $this->handleApproval($instance, $task);
            }

            return $task->refresh();
        });
    }

    public function canActOn(ApprovalTask $task, int $actor): bool
    {
        return $task->isPending() && $this->findHolderInfo((int) $task->auth_role_id, $actor) !== null;
    }

    // ── Internal step/task mechanics ───────────────────────────────────

    /**
     * Creates the task(s) for entering $step within $instance: one task
     * per workflowStepRole (so a sequential step gets exactly one, a
     * parallel step gets one per attached role). Each task that resolves
     * to zero active holders is created directly as 'on_hold' and
     * triggers the automatic admin notification (decision #4/#12).
     */
    private function enterStep(ApprovalInstance $instance, WorkflowStep $step): void
    {
        $instance->update(['current_step_id' => $step->id]);

        // Phase 7 (decision #13): each new step gets a fresh nudge
        // allowance — a user who nudged step 1 twice must still be able to
        // nudge step 2's (different) role. Runs at creation/resubmission
        // too, which is equally correct (fresh run = fresh allowance).
        // NOTE: the column keeps its legacy `notify_manager_count` name;
        // renaming it would churn 6+ references for zero behavior gain.
        Contract::where('contract_id', $instance->contract_id)
            ->update(['notify_manager_count' => 0]);

        foreach ($step->workflowStepRoles as $stepRole) {
            $holders = $this->roleProxy->activeHoldersOf($stepRole->auth_role_id);

            $task = ApprovalTask::create([
                'instance_id'  => $instance->id,
                'step_id'      => $step->id,
                'auth_role_id' => $stepRole->auth_role_id,
                'status'       => empty($holders) ? 'on_hold' : 'pending',
            ]);

            if (empty($holders)) {
                $this->notifyAdminOfEmptyRole($instance, $stepRole->role_name_snapshot, $task);
            }
        }
    }

    private function handleApproval(ApprovalInstance $instance, ApprovalTask $task): void
    {
        $step = $task->step;
        $siblingTasks = ApprovalTask::where('instance_id', $instance->id)
            ->where('step_id', $step->id)
            ->get();

        $stillPending = $siblingTasks->contains(fn ($t) => in_array($t->status, ['pending', 'on_hold']));
        if ($stillPending) {
            // Parallel step, not everyone has acted yet — wait.
            return;
        }

        // Every sibling task at this step is approved (none rejected, or
        // handleRejection() would already have short-circuited the step).
        $this->advanceToNextStep($instance, $step);
    }

    private function advanceToNextStep(ApprovalInstance $instance, WorkflowStep $completedStep): void
    {
        $workflow = $instance->workflow()->with('steps.workflowStepRoles')->first();
        $steps = $workflow->steps->sortBy('order_index')->values();
        $currentIndex = $steps->search(fn ($s) => $s->id === $completedStep->id);
        $nextStep = $currentIndex !== false ? $steps->get($currentIndex + 1) : null;

        if ($nextStep) {
            $this->enterStep($instance, $nextStep);
            return;
        }

        // No more steps — the chain is fully approved.
        $instance->update(['status' => 'approved', 'current_step_id' => null]);
        $this->applyContractOutcome($instance, 'Approved');
    }

    /**
     * Spec 5.4: a rejection at a parallel step fails the WHOLE step
     * immediately — sibling tasks that haven't acted yet are canceled
     * (not rejected), and the instance moves to revisions_required.
     */
    private function handleRejection(ApprovalInstance $instance, ApprovalTask $rejectedTask): void
    {
        ApprovalTask::where('instance_id', $instance->id)
            ->where('step_id', $rejectedTask->step_id)
            ->whereIn('status', ['pending', 'on_hold'])
            ->update(['status' => 'canceled']);

        $instance->update(['status' => 'revisions_required']);
        $this->applyContractOutcome($instance, 'Rejected', $rejectedTask->comment);
    }

    private function applyContractOutcome(ApprovalInstance $instance, string $outcome, ?string $rejectionReason = null): void
    {
        $contract = Contract::find($instance->contract_id);
        if (!$contract) {
            return;
        }

        $statusRow = ContractApprovalStatus::firstOrCreate(['status_name' => $outcome]);

        $updates = ['approval_status_id' => $statusRow->approval_status_id];
        if ($contract->execution_required) {
            $updates['workflow_status_id'] = $outcome === 'Approved'
                ? \App\Models\ContractStatus::where('status_name', 'SBSI Review')->value('status_id') : null;
            $updates['rejection_reason'] = null;
            $updates['internal_approved_at'] = $outcome === 'Approved' ? now() : null;
        }
        if ($outcome === 'Rejected') {
            $updates['rejection_reason'] = $rejectionReason;
            $updates['notify_manager_count'] = 0;
        }

        $contract->update($updates);
        if ($contract->execution_required) {
            \App\Jobs\SyncContractToMeilisearch::dispatch((int) $contract->getKey())->afterCommit();
        }
    }

    /**
     * Confirms $actingUserId is a current holder of $authRoleId (primary
     * assignment or active delegation), returning that holder's basic
     * info or null if they are not currently eligible.
     */
    private function findHolderInfo(int $authRoleId, int $actingUserId): ?array
    {
        $holders = $this->roleProxy->activeHoldersOf($authRoleId);
        foreach ($holders as $holder) {
            if ((int) ($holder['id'] ?? 0) === $actingUserId) {
                return $holder;
            }
        }
        return null;
    }

    private function notifyAdminOfEmptyRole(ApprovalInstance $instance, string $roleName, ApprovalTask $task): void
    {
        try {
            $contract = Contract::find($instance->contract_id);
            $message = sprintf(
                'Contract %s is on hold: no active user currently holds the "%s" role required at this approval step.',
                $contract?->bp_name ?? "#{$instance->contract_id}",
                $roleName
            );

            $this->notificationService->push(
                (int) $instance->contract_id,
                'approval_role_empty',
                $message,
                'Admin'
            );
        } catch (\Exception $e) {
            Log::error('Failed to push empty-role admin notification: ' . $e->getMessage());
        }
    }
}
