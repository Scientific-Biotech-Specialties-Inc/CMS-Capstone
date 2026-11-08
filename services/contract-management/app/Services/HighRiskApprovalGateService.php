<?php

namespace App\Services;

use App\Models\ContractApproval;
use Illuminate\Support\Carbon;

/**
 * US-023: Approve High-Risk Contracts.
 *
 * Encapsulates the mandatory approval gate: a contract flagged High/Critical
 * risk by ai-service's AI Risk Assessment cannot be marked "Approved" (which
 * drives this system's derived "Active" lifecycle status — see
 * Contract::lifecycleStatus()) until a contract_approvals row with
 * decision = 'approved' exists for it.
 *
 * The assessment itself is advisory (per the confirmed implementation plan),
 * but once a High/Critical result exists, this specific gate is a hard block,
 * matching US-023's AC ("Active status locked until approval recorded").
 */
class HighRiskApprovalGateService
{
    public function __construct(protected AiRiskService $aiRiskService)
    {
    }

    /**
     * Returns ['blocked' => bool, 'risk_level' => ?string, 'approval' => ?ContractApproval].
     *
     * If the contract is currently High/Critical risk, ensures a
     * contract_approvals row exists to track it (creating a pending one with
     * its 24h SLA due time if this is the first time we've seen the flag),
     * then reports whether an "approved" decision has been recorded yet.
     */
    public function checkGate(int $contractId): array
    {
        $riskLevel = $this->aiRiskService->getLatestRiskLevel($contractId);

        if (!$this->aiRiskService->isHighRisk($riskLevel)) {
            return ['blocked' => false, 'risk_level' => $riskLevel, 'approval' => null];
        }

        $approval = $this->ensurePendingApprovalRecord($contractId, $riskLevel);

        return [
            'blocked'    => $approval->decision !== 'approved',
            'risk_level' => $riskLevel,
            'approval'   => $approval,
        ];
    }

    /**
     * Returns the current contract_approvals row tracking this contract's
     * High/Critical flag — the most recent one if any exists (decided or
     * not), or a freshly created pending one (with a new 24h SLA clock) if
     * this is the first time the contract has been seen at High/Critical risk.
     *
     * Once a decision is recorded, this keeps returning that same decided
     * row (rather than spawning a new pending record) so the gate correctly
     * stays unblocked. A genuinely new gate cycle (e.g. a re-scan after an
     * amendment) is expected to be handled by future work that explicitly
     * resets the approval when the underlying risk assessment changes.
     */
    public function ensurePendingApprovalRecord(int $contractId, string $riskLevel): ContractApproval
    {
        $existing = ContractApproval::where('contract_id', $contractId)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $now = Carbon::now();

        return ContractApproval::create([
            'contract_id' => $contractId,
            'risk_level'  => $riskLevel,
            'flagged_at'  => $now,
            'sla_due_at'  => $now->copy()->addHours(24),
        ]);
    }
}
