<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 4 (Analytics): covers the internal notification metrics snapshot
 * endpoint used by analytics-service's descriptive aggregation.
 */
class InternalMetricsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function secretHeader(): array
    {
        return ['X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET', '')];
    }

    public function test_request_without_secret_is_rejected()
    {
        $response = $this->getJson('/api/internal/metrics/notifications');
        $response->assertStatus(401);
    }

    public function test_returns_notification_metrics_snapshot()
    {
        $response = $this->withHeaders($this->secretHeader())->getJson('/api/internal/metrics/notifications');

        $response->assertOk();
        $response->assertJsonStructure([
            'total_notifications', 'total_emails_sent', 'total_emails_failed',
            'email_success_rate_pct', 'notifications_by_type',
        ]);
    }
}
