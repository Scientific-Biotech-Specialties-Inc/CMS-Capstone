<?php

namespace App\Http\Controllers;

use App\Models\AggregatedMetric;
use App\Models\DiagnosticInsight;
use App\Models\PredictiveInsight;
use App\Services\DescriptiveAggregationService;
use App\Services\DiagnosticAnalysisService;
use App\Services\PredictiveAnalysisService;
use Illuminate\Http\Request;

/**
 * Feature 4: Analytics — Admin/Manager-only (enforced by role check here +
 * the frontend router guard). Descriptive + diagnostic + predictive read endpoints, plus
 * a manual refresh trigger for on-demand aggregation instead of waiting for
 * the hourly schedule.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        protected DescriptiveAggregationService $descriptive,
        protected DiagnosticAnalysisService $diagnostic,
        protected PredictiveAnalysisService $predictive,
    ) {
    }

    /**
     * GET /analytics/summary — latest descriptive metrics, grouped by
     * source_service and metric_type.
     */
    public function summary(Request $request)
    {
        if ($denied = $this->denyIfNotAuthorized($request)) {
            return $denied;
        }

        $latestDate = AggregatedMetric::max('metric_date');

        $metrics = AggregatedMetric::when($latestDate, fn ($q) => $q->where('metric_date', $latestDate))
            ->get()
            ->groupBy('source_service')
            ->map(fn ($group) => $group->map(fn ($m) => [
                'metric_type'  => $m->metric_type,
                'metric_value' => $m->metric_value,
                'metadata'     => $m->metadata,
            ])->values());

        return response()->json([
            'data' => [
                'as_of'   => $latestDate,
                'metrics' => $metrics,
            ],
        ]);
    }

    /**
     * GET /analytics/diagnostics — latest diagnostic insights per metric_type.
     */
    public function diagnostics(Request $request)
    {
        if ($denied = $this->denyIfNotAuthorized($request)) {
            return $denied;
        }

        $insights = DiagnosticInsight::orderByDesc('generated_at')
            ->get()
            ->groupBy('metric_type')
            ->map(fn ($group) => $group->first())
            ->values()
            ->map(fn ($i) => [
                'metric_type'     => $i->metric_type,
                'period_start'    => $i->period_start->toDateString(),
                'period_end'      => $i->period_end->toDateString(),
                'finding_summary' => $i->finding_summary,
                'ai_narrative'    => $i->ai_narrative,
                'generated_at'    => $i->generated_at->toISOString(),
            ]);

        return response()->json(['data' => $insights]);
    }

    /**
     * GET /analytics/predictive — latest 30-day forecast predictions per metric_type.
     */
    public function predictive(Request $request)
    {
        if ($denied = $this->denyIfNotAuthorized($request)) {
            return $denied;
        }

        $insights = PredictiveInsight::orderByDesc('generated_at')
            ->get()
            ->groupBy('metric_type')
            ->map(fn ($group) => $group->first())
            ->values()
            ->map(fn ($p) => [
                'metric_type'           => $p->metric_type,
                'forecast_date'         => $p->forecast_date->toDateString(),
                'forecast_horizon_days' => $p->forecast_horizon_days,
                'historical_series'     => $p->historical_series ?? [],
                'predicted_series'      => $p->predicted_series ?? [],
                'confidence'            => $p->confidence,
                'ai_narrative'          => $p->ai_narrative,
                'generated_at'          => $p->generated_at->toISOString(),
            ]);

        return response()->json(['data' => $insights]);
    }

    /**
     * POST /analytics/refresh — manually trigger an aggregation pass
     * (Admin/Manager only, same as read access) rather than waiting for the
     * hourly schedule.
     */
    public function refresh(Request $request)
    {
        if ($denied = $this->denyIfNotAuthorized($request)) {
            return $denied;
        }

        $written = $this->descriptive->runAll();
        $insights = $this->diagnostic->runAll();
        $predictions = $this->predictive->runAll();

        return response()->json([
            'message'              => 'Analytics refreshed.',
            'metrics_written'      => $written,
            'insights_generated'   => count($insights),
            'predictions_generated' => count($predictions),
        ]);
    }

    private function denyIfNotAuthorized(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $role = $request->get('auth_role');
        if (!in_array($role, ['Admin', 'Manager'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }
}
