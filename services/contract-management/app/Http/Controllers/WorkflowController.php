<?php

namespace App\Http\Controllers;

use App\Models\WorkflowStep;
use App\Services\RoleProxyService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin-only Workflow Builder endpoints (Phase 4). Gated by 'role:Admin'
 * in routes/api.php, matching the existing CMS admin-only pages.
 */
class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowService $workflowService,
        protected RoleProxyService $roleProxy,
    ) {
    }

    /**
     * GET /workflows/contract-types — list every contract type with its
     * active/draft workflow summary, for the builder's landing list.
     */
    public function contractTypes(Request $request)
    {
        return response()->json([
            'data' => $this->workflowService->listContractTypesWithWorkflows(),
        ]);
    }

    /**
     * GET /workflows/assignable-roles — proxies auth-module's role list
     * for the Role Picker dialog.
     */
    public function assignableRoles(Request $request)
    {
        $token = $request->bearerToken();
        $sessionId = $request->header('X-Session-ID') ?: $request->cookie('session_id');

        return response()->json([
            'data' => $this->roleProxy->listRoles($token, $sessionId),
        ]);
    }

    /**
     * GET /workflows/{id} — a single workflow with its steps and roles.
     */
    public function show(Request $request, $id)
    {
        return response()->json([
            'data' => $this->formatWorkflow($this->workflowService->getWorkflow((int) $id)),
        ]);
    }

    /**
     * POST /workflows — create a new workflow (draft or active).
     */
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $token = $request->bearerToken();
        $sessionId = $request->header('X-Session-ID') ?: $request->cookie('session_id');
        $actorId = (int) $request->get('auth_id');

        $workflow = $this->workflowService->saveWorkflow($validated, null, $actorId, $token, $sessionId);

        return response()->json(['data' => $this->formatWorkflow($workflow)], 201);
    }

    /**
     * PUT /workflows/{id} — update an existing workflow (full step replace).
     */
    public function update(Request $request, $id)
    {
        $validated = $this->validatePayload($request);

        $token = $request->bearerToken();
        $sessionId = $request->header('X-Session-ID') ?: $request->cookie('session_id');
        $actorId = (int) $request->get('auth_id');

        $workflow = $this->workflowService->saveWorkflow($validated, (int) $id, $actorId, $token, $sessionId);

        return response()->json(['data' => $this->formatWorkflow($workflow)]);
    }

    /**
     * POST /workflows/{id}/duplicate — one-time duplication onto a
     * different contract type, as a new draft (decision #10).
     */
    public function duplicate(Request $request, $id)
    {
        $request->validate([
            'target_contract_type_id' => 'required|integer|exists:contract_categories,category_id',
            'name' => 'required|string|max:255',
        ]);

        $actorId = (int) $request->get('auth_id');

        $workflow = $this->workflowService->duplicateWorkflow(
            (int) $id,
            (int) $request->input('target_contract_type_id'),
            $request->input('name'),
            $actorId
        );

        return response()->json(['data' => $this->formatWorkflow($workflow)], 201);
    }

    /**
     * DELETE /workflows/{id} — only allowed if no contract has ever run
     * through it (no approval_instances reference it).
     */
    public function destroy(Request $request, $id)
    {
        $this->workflowService->deleteWorkflow((int) $id);
        return response()->json(['message' => 'Workflow deleted successfully.']);
    }

    private function validatePayload(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'contract_type_id'            => 'required|integer|exists:contract_categories,category_id',
            'name'                        => 'required|string|max:255',
            'status'                      => 'required|string|in:draft,active',
            'replace_active_workflow_id'  => 'sometimes|prohibited_unless:status,active|integer|min:1',
            'replace_draft_workflow_id'   => 'sometimes|integer|min:1',
            'resubmit_mode'               => 'nullable|string|in:restart,resume_at_rejected',
            'steps'                       => 'array',
            'steps.*.order_index'         => 'required_with:steps|integer|min:1',
            'steps.*.mode'                => 'required_with:steps|string|in:sequential,parallel',
            'steps.*.roles'               => 'array',
            'steps.*.roles.*.auth_role_id' => 'required_with:steps.*.roles|integer',
            'steps.*.roles.*.role_name'    => 'required_with:steps.*.roles|string',
            'execution_reviewers'         => 'nullable|array:SBSI Review,Client Review,For Signing,Signed',
            'execution_reviewers.*'       => 'array|max:10',
            'execution_reviewers.*.*.auth_role_id' => 'required|integer|min:1',
            'execution_reviewers.*.*.role_name' => 'required|string|max:255',
        ]);

        return $validator->validate();
    }

    private function formatWorkflow($workflow): array
    {
        return [
            'id'               => $workflow->id,
            'contract_type_id' => $workflow->contract_type_id,
            'name'             => $workflow->name,
            'status'           => $workflow->status,
            'has_runs'         => $workflow->instances()->exists(),
            'resubmit_mode'    => $workflow->resubmit_mode,
            'execution_reviewers' => $workflow->execution_reviewers,
            'steps'            => $workflow->steps->map(fn (WorkflowStep $step) => [
                'id'          => $step->id,
                'order_index' => $step->order_index,
                'mode'        => $step->mode,
                'roles'       => $step->workflowStepRoles->map(fn ($r) => [
                    'id'            => $r->id,
                    'auth_role_id'  => $r->auth_role_id,
                    'role_name'     => $r->role_name_snapshot,
                ])->values(),
            ])->values(),
        ];
    }
}
