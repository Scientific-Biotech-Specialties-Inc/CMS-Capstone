<?php

namespace App\Services;

use App\Models\AggregatedMetric;
use Illuminate\Support\Facades\Log;

/**
 * Feature 4: Descriptive analytics ("what happened"). Pulls metrics
 * snapshots from every source service and writes them into
 * aggregated_metrics, one row per metric_type/source_service/date — matching
 * the pattern the original candidate schema was designed for.
 */
class DescriptiveAggregationService
{
    public function __construct(protected MetricsClient $client)
    {
    }

    public function runAll(): array
    {
        $today = now()->toDateString();
        $written = [];

        $written['contracts']     = $this->aggregateContracts($today);
        $written['vendors']       = $this->aggregateVendors($today);
        $written['notifications'] = $this->aggregateNotifications($today);
        $written['ai']            = $this->aggregateAi($today);

        return $written;
    }

    protected function aggregateContracts(string $date): int
    {
        $data = $this->client->contractMetrics();
        if ($data === null) {
            Log::warning('DescriptiveAggregationService: contract metrics unavailable, skipping.');
            return 0;
        }

        $count = 0;
        $count += $this->write('contracts_total', 'contract-management', $data['total_contracts'] ?? null, $date);
        $count += $this->write('contracts_expiring_soon_30d', 'contract-management', $data['expiring_soon_30d'] ?? null, $date);
        $count += $this->write('contracts_expired', 'contract-management', $data['expired'] ?? null, $date);
        $count += $this->write('contracts_avg_approval_hours', 'contract-management', $data['avg_approval_time_hours'] ?? null, $date);
        $count += $this->write('high_risk_approvals_pending', 'contract-management', $data['high_risk_approvals_pending'] ?? null, $date);
        $count += $this->write('high_risk_approvals_escalated', 'contract-management', $data['high_risk_approvals_escalated'] ?? null, $date, [
            'by_status'   => $data['by_status'] ?? null,
            'by_category' => $data['by_category'] ?? null,
            'by_region'   => $data['by_region'] ?? null,
            'renewal_pipeline_by_month' => $data['renewal_pipeline_by_month'] ?? null,
        ]);

        return $count;
    }

    protected function aggregateVendors(string $date): int
    {
        $data = $this->client->vendorMetrics();
        if ($data === null) {
            Log::warning('DescriptiveAggregationService: vendor metrics unavailable, skipping.');
            return 0;
        }

        $count = 0;
        $count += $this->write('suppliers_total', 'vendor-management', $data['total_suppliers'] ?? null, $date);
        $count += $this->write('partners_total', 'vendor-management', $data['total_partners'] ?? null, $date, [
            'suppliers_by_region'   => $data['suppliers_by_region'] ?? null,
            'partners_by_region'    => $data['partners_by_region'] ?? null,
            'suppliers_by_status'   => $data['suppliers_by_status'] ?? null,
            'partners_by_status'    => $data['partners_by_status'] ?? null,
            'suppliers_by_industry' => $data['suppliers_by_industry'] ?? null,
            'partners_by_industry'  => $data['partners_by_industry'] ?? null,
        ]);

        return $count;
    }

    protected function aggregateNotifications(string $date): int
    {
        $data = $this->client->notificationMetrics();
        if ($data === null) {
            Log::warning('DescriptiveAggregationService: notification metrics unavailable, skipping.');
            return 0;
        }

        $count = 0;
        $count += $this->write('notifications_total', 'notification', $data['total_notifications'] ?? null, $date);
        $count += $this->write('emails_sent', 'notification', $data['total_emails_sent'] ?? null, $date);
        $count += $this->write('emails_failed', 'notification', $data['total_emails_failed'] ?? null, $date);
        $count += $this->write('email_success_rate_pct', 'notification', $data['email_success_rate_pct'] ?? null, $date, [
            'notifications_by_type' => $data['notifications_by_type'] ?? null,
        ]);

        return $count;
    }

    protected function aggregateAi(string $date): int
    {
        $data = $this->client->aiMetrics();
        if ($data === null) {
            Log::warning('DescriptiveAggregationService: ai metrics unavailable, skipping.');
            return 0;
        }

        $count = 0;
        $count += $this->write('contracts_scanned', 'ai-service', $data['contracts_scanned'] ?? null, $date);
        $count += $this->write('scans_completed', 'ai-service', $data['scans_completed'] ?? null, $date);
        $count += $this->write('scans_failed', 'ai-service', $data['scans_failed'] ?? null, $date);

        // ai-service computes risk_score on a 0-100 severity-weighted scale
        // (low=10, medium=40, high=70, critical=95), but analytics documents
        // and displays "Average Contract Risk Score" on a 0-10 scale
        // everywhere (labels, historical baselines, forecast unit). Normalize
        // here at the single write point so the two scales can never mix in
        // aggregated_metrics again.
        $rawAvgRiskScore = $data['avg_risk_score'] ?? null;
        $normalizedAvgRiskScore = is_numeric($rawAvgRiskScore) ? round($rawAvgRiskScore / 10, 2) : null;

        $count += $this->write('avg_risk_score', 'ai-service', $normalizedAvgRiskScore, $date, [
            'by_risk_level'               => $data['by_risk_level'] ?? null,
            'most_cited_playbook_clauses' => $data['most_cited_playbook_clauses'] ?? null,
        ]);
        $count += $this->write('vendor_suggestions_total', 'ai-service', $data['vendor_suggestions_total'] ?? null, $date);
        $count += $this->write('vendor_suggestions_accepted', 'ai-service', $data['vendor_suggestions_accepted'] ?? null, $date);

        return $count;
    }

    protected function write(string $metricType, string $sourceService, mixed $value, string $date, ?array $metadata = null): int
    {
        if ($value === null && $metadata === null) {
            return 0;
        }

        AggregatedMetric::updateOrCreate(
            ['metric_type' => $metricType, 'source_service' => $sourceService, 'metric_date' => $date],
            ['metric_value' => is_numeric($value) ? $value : null, 'metadata' => $metadata]
        );

        return 1;
    }
}
