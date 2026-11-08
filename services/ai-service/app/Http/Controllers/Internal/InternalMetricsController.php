<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\RiskAssessmentFinding;
use App\Models\RiskAssessmentResult;
use App\Models\VendorSuggestionCandidate;
use Illuminate\Http\Request;

/**
 * Internal, X-Internal-Secret-authenticated metrics snapshot for
 * analytics-service's descriptive/diagnostic aggregation (Feature 4).
 * Returns aggregate counts only — never raw finding text, embeddings, or
 * other stored AI content, matching the same "existence/counts only"
 * principle already used by SchemaHealthController.
 */
class InternalMetricsController extends Controller
{
    public function metrics(Request $request)
    {
        $totalScanned = RiskAssessmentResult::count();
        $completed = RiskAssessmentResult::where('status', 'completed')->count();
        $failed = RiskAssessmentResult::where('status', 'failed')->count();

        $byRiskLevel = RiskAssessmentResult::whereNotNull('risk_level')
            ->select('risk_level')->get()->countBy('risk_level');

        $avgRiskScore = RiskAssessmentResult::whereNotNull('risk_score')->avg('risk_score');

        $mostCitedClauses = RiskAssessmentFinding::selectRaw('playbook_clause_id, count(*) as count')
            ->whereNotNull('playbook_clause_id')
            ->groupBy('playbook_clause_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json([
            'contracts_scanned'       => $totalScanned,
            'scans_completed'         => $completed,
            'scans_failed'            => $failed,
            'by_risk_level'           => $byRiskLevel,
            'avg_risk_score'          => $avgRiskScore !== null ? round((float) $avgRiskScore, 2) : null,
            'most_cited_playbook_clauses' => $mostCitedClauses,
            'vendor_suggestions_total'    => VendorSuggestionCandidate::count(),
            'vendor_suggestions_accepted' => VendorSuggestionCandidate::where('decision', 'accepted')->count(),
            'vendor_suggestions_dismissed' => VendorSuggestionCandidate::where('decision', 'dismissed')->count(),
        ]);
    }
}
