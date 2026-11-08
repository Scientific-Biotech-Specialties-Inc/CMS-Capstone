<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractApproval;
use App\Services\AiRiskService;
use App\Services\AuditLogService;
use App\Services\HighRiskApprovalGateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * US-023: Approve High-Risk Contracts.
 *
 * Handles recording the mandatory approver decision (approve/reject) with
 * rationale for a High/Critical-risk contract, and exposes the current gate
 * state for the frontend's LockedStatusBadge.
 */
class ContractApprovalController extends Controller
{
    public function __construct(
        protected HighRiskApprovalGateService $gateService,
        protected AiRiskService $aiRiskService,
        protected AuditLogService $auditLogService,
    ) {
    }

    /**
     * GET /contracts/{id}/high-risk-approval — current gate state for the contract.
     *
     * DISABLED BY DEFAULT (see config('services.features.high_risk_approval_gate_enabled')).
     * While disabled, returns risk_level (still useful for the frontend's
     * advisory flag/warning) but never creates a contract_approvals row and
     * always reports blocked=false, so the SLA clock never starts.
     */
    public function show(Request $request, $id)
    {
        $contract = Contract::where('contract_code', $id)->orWhere('contract_id', $id)->firstOrFail();

        if (!config('services.features.high_risk_approval_gate_enabled')) {
            $riskLevel = $this->aiRiskService->getLatestRiskLevel((int) $contract->contract_id);

            return response()->json([
                'data' => [
                    'contract_id'   => (int) $contract->contract_id,
                    'risk_level'    => $riskLevel,
                    'requires_high_risk_approval' => false,
                    'blocked'       => false,
                    'approval'      => null,
                ],
            ]);
        }

        $result = $this->gateService->checkGate((int) $contract->contract_id);

        return response()->json([
            'data' => [
                'contract_id'   => (int) $contract->contract_id,
                'risk_level'    => $result['risk_level'],
                'requires_high_risk_approval' => $this->aiRiskService->isHighRisk($result['risk_level']),
                'blocked'       => $result['blocked'],
                'approval'      => $result['approval'] ? $this->formatApproval($result['approval']) : null,
            ],
        ]);
    }

    /**
     * POST /contracts/{id}/high-risk-approval — record the approver's decision.
     *
     * DISABLED BY DEFAULT — see class docblock.
     */
    public function store(Request $request, $id)
    {
        if (!config('services.features.high_risk_approval_gate_enabled')) {
            return response()->json([
                'message' => 'The high-risk approval gate is currently disabled. AI Risk Assessment is advisory only.',
            ], 404);
        }

        $contract = Contract::where('contract_code', $id)->orWhere('contract_id', $id)->firstOrFail();

        // Separation of Duties: a contract creator cannot record high-risk approval decisions on their own contract.
        if (
            config('services.features.enforce_sod')
            && $contract->created_by !== null
            && (int) $contract->created_by === (int) $request->get('auth_id')
        ) {
            return response()->json([
                'message' => 'You cannot record a high-risk approval decision on a contract you created.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'decision'  => 'required|string|in:approved,rejected',
            'rationale' => 'required|string|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $riskLevel = $this->aiRiskService->getLatestRiskLevel((int) $contract->contract_id);

        if (!$this->aiRiskService->isHighRisk($riskLevel)) {
            return response()->json([
                'message' => 'This contract is not currently flagged High/Critical risk; no high-risk approval is required.',
            ], 422);
        }

        $approval = $this->gateService->ensurePendingApprovalRecord((int) $contract->contract_id, $riskLevel);

        if ($approval->decision !== null) {
            return response()->json([
                'message' => 'A decision has already been recorded for this high-risk flag.',
            ], 422);
        }

        $oldData = $approval->toArray();

        $approverId = $request->get('auth_id');

        $approval->update([
            'approver_id' => $approverId,
            'rationale'   => $request->input('rationale'),
            'decision'    => $request->input('decision'),
            'decided_at'  => now(),
        ]);

        $this->auditLogService->log(
            'high_risk_approval_' . $request->input('decision'),
            'ContractApproval',
            $approval->id,
            $approverId,
            $oldData,
            $approval->toArray(),
            $request->get('auth_department')
        );

        return response()->json([
            'message' => 'High-risk approval decision recorded.',
            'data'    => $this->formatApproval($approval->fresh()),
        ]);
    }

    private function formatApproval(ContractApproval $approval): array
    {
        return [
            'id'           => $approval->id,
            'contract_id'  => $approval->contract_id,
            'risk_level'   => $approval->risk_level,
            'approver_id'  => $approval->approver_id,
            'rationale'    => $approval->rationale,
            'decision'     => $approval->decision,
            'decided_at'   => $approval->decided_at?->toISOString(),
            'flagged_at'   => $approval->flagged_at?->toISOString(),
            'sla_due_at'   => $approval->sla_due_at?->toISOString(),
            'escalated_at' => $approval->escalated_at?->toISOString(),
        ];
    }
}
