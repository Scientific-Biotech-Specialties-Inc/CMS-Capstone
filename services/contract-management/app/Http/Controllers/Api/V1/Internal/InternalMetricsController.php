<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Internal, X-Internal-Secret-authenticated metrics snapshot used by
 * analytics-service's descriptive/diagnostic aggregation (Feature 4). Read
 * only — never exposed to end users directly, and no PII beyond aggregate
 * counts is returned.
 */
class InternalMetricsController extends Controller
{
    public function contracts(Request $request)
    {
        $total = Contract::count();

        $byStatus = DB::table('contracts')
            ->join('contract_approval_statuses', 'contracts.approval_status_id', '=', 'contract_approval_statuses.approval_status_id')
            ->select('contract_approval_statuses.status_name', DB::raw('count(*) as count'))
            ->groupBy('contract_approval_statuses.status_name')
            ->pluck('count', 'status_name');

        $byCategory = DB::table('contracts')
            ->join('contract_categories', 'contracts.category_id', '=', 'contract_categories.category_id')
            ->select('contract_categories.category_name', DB::raw('count(*) as count'))
            ->groupBy('contract_categories.category_name')
            ->pluck('count', 'category_name');

        $byRegion = DB::table('contracts')
            ->join('contract_regions', 'contracts.region_id', '=', 'contract_regions.region_id')
            ->select('contract_regions.region_name', DB::raw('count(*) as count'))
            ->groupBy('contract_regions.region_name')
            ->pluck('count', 'region_name');

        $today = now()->toDateString();
        $in30Days = now()->addDays(30)->toDateString();

        $expiringSoon = Contract::dateMonitored()
            ->whereBetween('end_date', [$today, $in30Days])
            ->count();

        $expired = Contract::dateMonitored()
            ->where('end_date', '<', $today)
            ->count();

        // Renewal pipeline: contracts expiring within the next 90 days, bucketed by month.
        $renewalPipeline = Contract::dateMonitored()
            ->whereBetween('end_date', [$today, now()->addDays(90)->toDateString()])
            ->get(['end_date'])
            ->groupBy(fn ($c) => $c->end_date->format('Y-m'))
            ->map->count();

        // Legacy approvals use their last update as the historical proxy.
        $avgApprovalHours = Contract::where('approval_status_id', 2)
            ->get(['created_at', 'updated_at', 'internal_approved_at'])
            ->map(fn ($c) => $c->created_at?->diffInHours($c->internal_approved_at ?? $c->updated_at))
            ->filter(fn ($h) => $h !== null)
            ->average();

        return response()->json([
            'total_contracts'   => $total,
            'by_status'         => $byStatus,
            'by_category'       => $byCategory,
            'by_region'         => $byRegion,
            'expiring_soon_30d' => $expiringSoon,
            'expired'           => $expired,
            'renewal_pipeline_by_month' => $renewalPipeline,
            'avg_approval_time_hours'   => $avgApprovalHours !== null ? round($avgApprovalHours, 2) : null,
            'high_risk_approvals_pending'   => ContractApproval::whereNull('decision')->count(),
            'high_risk_approvals_escalated' => ContractApproval::whereNotNull('escalated_at')->count(),
        ]);
    }

    /**
     * Diagnostic support: contract counts grouped by category+region for a
     * given date range, used by analytics-service's root-cause breakdown
     * (e.g. "which segment drove this period's risk-flag rate increase").
     */
    public function contractsBySegment(Request $request)
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        $query = DB::table('contracts')
            ->join('contract_categories', 'contracts.category_id', '=', 'contract_categories.category_id')
            ->join('contract_regions', 'contracts.region_id', '=', 'contract_regions.region_id')
            ->select(
                'contract_categories.category_name',
                'contract_regions.region_name',
                DB::raw('count(*) as count')
            )
            ->groupBy('contract_categories.category_name', 'contract_regions.region_name');

        if ($from) {
            $query->where('contracts.created_at', '>=', $from);
        }
        if ($to) {
            $query->where('contracts.created_at', '<=', $to);
        }

        return response()->json(['data' => $query->get()]);
    }
}
