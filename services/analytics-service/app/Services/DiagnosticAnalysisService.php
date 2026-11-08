<?php

namespace App\Services;

use App\Models\AggregatedMetric;
use App\Models\DiagnosticInsight;
use App\Services\Gemini\GeminiClient;
use Illuminate\Support\Carbon;

/**
 * Feature 4: Diagnostic analytics (the "why" layer). Two tracks:
 *
 * 1. Algorithmic (this class, deterministic, no LLM call): compares the
 *    current period's descriptive metrics against the prior period and
 *    surfaces which segment (category/region) is driving the change —
 *    a root-cause breakdown, not a black box.
 * 2. AI-assisted narrative (optional): once the algorithmic result exists,
 *    ask Gemini for a one-paragraph plain-language explanation of that
 *    already-computed structured result. Gemini summarizes facts here, it
 *    never computes the diagnosis itself.
 */
class DiagnosticAnalysisService
{
    public function __construct(protected GeminiClient $gemini, protected MetricsClient $metricsClient)
    {
    }

    public function runAll(): array
    {
        $insights = [];
        $insights[] = $this->analyzeRiskFlagRate();
        $insights[] = $this->analyzeApprovalSlaBottleneck();

        return array_filter($insights);
    }

    /**
     * Root-cause breakdown: "why did the risk-flag rate change this period,
     * and which contract segment (category x region) is driving it?"
     */
    protected function analyzeRiskFlagRate(): ?DiagnosticInsight
    {
        $periodEnd = Carbon::today();
        $periodStart = $periodEnd->copy()->subDays(30);
        $priorStart = $periodStart->copy()->subDays(30);

        $current = $this->latestMetricValue('avg_risk_score', $periodStart, $periodEnd);
        $prior = $this->latestMetricValue('avg_risk_score', $priorStart, $periodStart);

        if ($current === null) {
            return null;
        }

        $delta = $prior !== null ? round($current - $prior, 2) : null;
        $segmentBreakdown = $this->metricsClient->contractsBySegment();

        $findingSummary = [
            'metric'             => 'avg_risk_score',
            'current_period_avg' => $current,
            'prior_period_avg'   => $prior,
            'delta'              => $delta,
            'trend'              => $delta === null ? 'unknown' : ($delta > 0 ? 'worsening' : ($delta < 0 ? 'improving' : 'stable')),
            'segment_breakdown'  => $segmentBreakdown['data'] ?? [],
        ];

        $narrative = $this->gemini->generateText(
            'You are a data analyst assistant. You are given a structured JSON summary of a '
                . 'change in average contract risk score over the last 30 days versus the prior '
                . '30 days, plus a segment breakdown. Write ONE short plain-language paragraph '
                . '(2-3 sentences) explaining the finding. Do not invent numbers not present in the JSON.',
            json_encode($findingSummary)
        );

        return DiagnosticInsight::create([
            'metric_type'     => 'risk_flag_rate',
            'period_start'    => $periodStart->toDateString(),
            'period_end'      => $periodEnd->toDateString(),
            'finding_summary' => $findingSummary,
            'ai_narrative'    => $narrative,
            'generated_at'    => now(),
        ]);
    }

    /**
     * SLA bottleneck: which stage (proxied here by contract avg approval
     * time, since real per-stage timestamps beyond created/updated_at
     * aren't tracked yet) is driving approval-time changes.
     */
    protected function analyzeApprovalSlaBottleneck(): ?DiagnosticInsight
    {
        $periodEnd = Carbon::today();
        $periodStart = $periodEnd->copy()->subDays(30);
        $priorStart = $periodStart->copy()->subDays(30);

        $current = $this->latestMetricValue('contracts_avg_approval_hours', $periodStart, $periodEnd);
        $prior = $this->latestMetricValue('contracts_avg_approval_hours', $priorStart, $periodStart);

        if ($current === null) {
            return null;
        }

        $delta = $prior !== null ? round($current - $prior, 2) : null;
        $pendingHighRisk = $this->latestMetricValue('high_risk_approvals_pending', $periodStart, $periodEnd);
        $escalated = $this->latestMetricValue('high_risk_approvals_escalated', $periodStart, $periodEnd);

        $findingSummary = [
            'metric'                        => 'contracts_avg_approval_hours',
            'current_period_avg_hours'      => $current,
            'prior_period_avg_hours'        => $prior,
            'delta_hours'                   => $delta,
            'trend'                         => $delta === null ? 'unknown' : ($delta > 0 ? 'slower' : ($delta < 0 ? 'faster' : 'stable')),
            'high_risk_approvals_pending'   => $pendingHighRisk,
            'high_risk_approvals_escalated' => $escalated,
        ];

        $narrative = $this->gemini->generateText(
            'You are a data analyst assistant. You are given a structured JSON summary of a '
                . 'change in average contract approval time over the last 30 days versus the '
                . 'prior 30 days, plus pending/escalated high-risk approval counts. Write ONE '
                . 'short plain-language paragraph (2-3 sentences) explaining the finding. Do not '
                . 'invent numbers not present in the JSON.',
            json_encode($findingSummary)
        );

        return DiagnosticInsight::create([
            'metric_type'     => 'approval_sla_bottleneck',
            'period_start'    => $periodStart->toDateString(),
            'period_end'      => $periodEnd->toDateString(),
            'finding_summary' => $findingSummary,
            'ai_narrative'    => $narrative,
            'generated_at'    => now(),
        ]);
    }

    protected function latestMetricValue(string $metricType, Carbon $start, Carbon $end): ?float
    {
        // whereDate() (rather than a raw whereBetween on date strings) is
        // required here because the `date` cast on AggregatedMetric
        // serializes metric_date as "Y-m-d H:i:s" when persisting, so a
        // plain string comparison against date-only bounds would
        // incorrectly exclude same-day rows.
        $value = AggregatedMetric::where('metric_type', $metricType)
            ->whereDate('metric_date', '>=', $start->toDateString())
            ->whereDate('metric_date', '<=', $end->toDateString())
            ->orderByDesc('metric_date')
            ->value('metric_value');

        return $value !== null ? (float) $value : null;
    }
}
