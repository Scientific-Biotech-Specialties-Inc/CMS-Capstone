<?php

namespace Tests\Feature;

use App\Services\DescriptiveAggregationService;
use App\Services\MetricsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature 4: covers that a full aggregation pass writes aggregated_metrics
 * rows from all four source services' snapshots, and degrades gracefully
 * (writes 0 for that source, doesn't throw) when a source is unreachable.
 */
class DescriptiveAggregationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_metrics_from_all_source_services(): void
    {
        Http::fake([
            'http://contract-management:8000/api/internal/metrics/contracts' => Http::response([
                'total_contracts' => 42,
                'expiring_soon_30d' => 3,
                'expired' => 1,
                'avg_approval_time_hours' => 12.5,
                'high_risk_approvals_pending' => 2,
                'high_risk_approvals_escalated' => 0,
                'by_status' => ['Approved' => 40],
                'by_category' => ['Service Agreement' => 42],
                'by_region' => ['Luzon' => 42],
                'renewal_pipeline_by_month' => [],
            ], 200),
            'http://vendor-management:8000/api/internal/metrics/vendors' => Http::response([
                'total_suppliers' => 10,
                'total_partners' => 5,
                'suppliers_by_region' => [],
                'partners_by_region' => [],
                'suppliers_by_status' => [],
                'partners_by_status' => [],
                'suppliers_by_industry' => [],
                'partners_by_industry' => [],
            ], 200),
            'http://notification:8000/api/internal/metrics/notifications' => Http::response([
                'total_notifications' => 100,
                'total_emails_sent' => 90,
                'total_emails_failed' => 10,
                'email_success_rate_pct' => 90.0,
                'notifications_by_type' => [],
            ], 200),
            'http://ai-service:8000/api/internal/metrics/ai' => Http::response([
                'contracts_scanned' => 20,
                'scans_completed' => 18,
                'scans_failed' => 2,
                'by_risk_level' => ['high' => 5],
                'avg_risk_score' => 45.5,
                'most_cited_playbook_clauses' => [],
                'vendor_suggestions_total' => 15,
                'vendor_suggestions_accepted' => 8,
                'vendor_suggestions_dismissed' => 4,
            ], 200),
        ]);

        $service = app(DescriptiveAggregationService::class);
        $written = $service->runAll();

        $this->assertGreaterThan(0, $written['contracts']);
        $this->assertGreaterThan(0, $written['vendors']);
        $this->assertGreaterThan(0, $written['notifications']);
        $this->assertGreaterThan(0, $written['ai']);

        $this->assertDatabaseHas('aggregated_metrics', [
            'metric_type'    => 'contracts_total',
            'source_service' => 'contract-management',
            'metric_value'   => 42,
        ]);
        $this->assertDatabaseHas('aggregated_metrics', [
            'metric_type'    => 'suppliers_total',
            'source_service' => 'vendor-management',
            'metric_value'   => 10,
        ]);
        $this->assertDatabaseHas('aggregated_metrics', [
            'metric_type'    => 'contracts_scanned',
            'source_service' => 'ai-service',
            'metric_value'   => 20,
        ]);

        // ai-service reports avg_risk_score on its internal 0-100
        // severity-weighted scale (45.5); this must be normalized to the
        // 0-10 scale analytics documents and displays everywhere before
        // being persisted.
        $this->assertDatabaseHas('aggregated_metrics', [
            'metric_type'    => 'avg_risk_score',
            'source_service' => 'ai-service',
            'metric_value'   => 4.55,
        ]);
    }

    public function test_degrades_gracefully_when_a_source_service_is_unreachable(): void
    {
        Http::fake([
            'http://contract-management:8000/api/internal/metrics/contracts' => Http::response([], 500),
            'http://vendor-management:8000/api/internal/metrics/vendors'     => Http::response([], 500),
            'http://notification:8000/api/internal/metrics/notifications'    => Http::response([], 500),
            'http://ai-service:8000/api/internal/metrics/ai'                 => Http::response([], 500),
        ]);

        $service = app(DescriptiveAggregationService::class);
        $written = $service->runAll();

        $this->assertSame(['contracts' => 0, 'vendors' => 0, 'notifications' => 0, 'ai' => 0], $written);
        $this->assertDatabaseCount('aggregated_metrics', 0);
    }
}
