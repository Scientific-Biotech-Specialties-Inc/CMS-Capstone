<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Contracts\ApplyExecutionAction;
use App\Http\Requests\ContractExecutionRequest;
use App\Jobs\SyncContractToMeilisearch;
use App\Models\Contract;
use App\Models\ContractExecutionEvent;
use App\Models\ApprovalTask;
use App\Policies\ContractExecutionPolicy;
use App\Services\ApprovalEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ContractExecutionController extends Controller
{
    public function show(Request $request, string $id, ApplyExecutionAction $action, ContractExecutionPolicy $policy, ApprovalEngineService $approval): JsonResponse
    {
        $contract = $this->contract($request, $id, $policy);
        $stage = $contract->workflowStatus?->status_name;
        $enabled = $contract->execution_required && isset(ApplyExecutionAction::NEXT[$stage]);
        $owner = $policy->owns($request, $contract);
        $reviewer = $enabled && $policy->canConfirm($request, $action->reviewers($contract), $stage);
        $canManage = $policy->canManageRevision($request, $contract);
        $canSeeHistory = $canManage || $reviewer || in_array('cms.audit.view', $request->input('auth_permissions', []), true)
            || ($contract->execution_required && $this->hasApprovalAssignment($contract, (int) $request->input('auth_id'), $approval));
        $paused = $contract->revision_paused_at !== null;
        $pending = $enabled ? $contract->executionEvents()->where('state', 'pending')->first() : null;

        return response()->json(['data' => [
            'enabled' => $contract->execution_required,
            'stage' => $stage,
            'paused' => $paused,
            'draft_document_id' => $contract->execution_draft_id,
            'signed_document_id' => $contract->signed_document_id,
            'notarized_document_id' => $contract->notarized_document_id,
            'pending' => $pending && $canSeeHistory ? $this->event($pending) : null,
            'awaiting_confirmation' => $pending !== null,
            'history' => $canSeeHistory && $contract->execution_required
                ? $contract->executionEvents()->latest('id')->limit(100)->get()->map($this->event(...)) : [],
            'allowed' => [
                'submit' => $enabled && !$paused && !$pending && $owner,
                'confirm' => $enabled && !$paused && $pending && $reviewer && !$owner,
                'pause' => $enabled && !$paused && !$contract->signed_document_id && $canManage,
                'resume' => $paused && $canManage,
                'revise' => $paused && $owner,
                'auto_confirm' => $policy->hasBypass($request) && ($stage !== 'For Signing' || $reviewer),
            ],
        ]]);
    }

    public function store(ContractExecutionRequest $request, string $id, ApplyExecutionAction $action, ContractExecutionPolicy $policy): JsonResponse
    {
        $contract = $this->contract($request, $id, $policy);
        $event = $action->handle($contract, $request, $request->validated());
        SyncContractToMeilisearch::dispatch((int) $contract->getKey());

        return response()->json(['data' => $this->event($event)]);
    }

    private function contract(Request $request, string $id, ContractExecutionPolicy $policy): Contract
    {
        $contract = Contract::with('workflowStatus')->where('contract_code', $id)
            ->orWhere('contract_id', ctype_digit($id) ? (int) $id : 0)->firstOrFail();
        abort_if(in_array($request->input('auth_role'), ['Sales', 'Employee'], true) && !$policy->owns($request, $contract), 403);

        return $contract;
    }

    private function event(ContractExecutionEvent $event): array
    {
        return $event->only(['id', 'action', 'stage', 'state', 'draft_document_id', 'document_id',
            'submitted_by', 'decided_by', 'created_at', 'decided_at', 'reason']);
    }

    private function hasApprovalAssignment(Contract $contract, int $actor, ApprovalEngineService $approval): bool
    {
        $run = $contract->approvalInstances()->latest('run_number')->first();
        if (!$run) {
            return false;
        }

        return $run->tasks()->where('acted_by_user_id', $actor)->exists()
            || ($run->isInProgress() && $run->tasks()->where('step_id', $run->current_step_id)->where('status', 'pending')
                ->get()->contains(fn (ApprovalTask $task): bool => $approval->canActOn($task, $actor)));
    }
}
