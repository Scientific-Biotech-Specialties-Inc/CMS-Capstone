<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /internal/contracts/{contractId}/risk-level — read-only lookup used by
 * contract-management's HighRiskApprovalGateService (US-023) to decide
 * whether a contract requires a mandatory high-risk approval before it can
 * be marked Active. Returns only the risk_level string — never findings,
 * scores, or any other stored assessment content.
 */
class ContractRiskLevelController extends Controller
{
    public function show(int $contractId): JsonResponse
    {
        $latest = DB::table('risk_assessment_results')
            ->where('contract_id', $contractId)
            ->where('status', 'completed')
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            return response()->json(['message' => 'No completed risk assessment found for this contract.'], 404);
        }

        return response()->json([
            'contract_id' => $contractId,
            'risk_level'  => $latest->risk_level,
        ]);
    }
}
