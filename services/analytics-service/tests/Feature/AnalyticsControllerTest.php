<?php

namespace Tests\Feature;

use App\Models\AggregatedMetric;
use App\Models\DiagnosticInsight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature 4: covers the Admin/Manager-only role gate and that
 * /analytics/summary and /analytics/diagnostics return the expected shapes.
 */
class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAuth(string $role): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user'  => ['id' => 1, 'role' => $role, 'permissions' => []],
            ], 200),
        ]);
    }

    public function test_sales_role_is_forbidden(): void
    {
        $this->fakeAuth('Sales');

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/analytics/summary');

        $response->assertStatus(403);
    }

    public function test_manager_can_view_summary(): void
    {
        $this->fakeAuth('Manager');

        AggregatedMetric::create([
            'metric_type' => 'contracts_total', 'source_service' => 'contract-management',
            'metric_value' => 42, 'metric_date' => now()->toDateString(),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/analytics/summary');

        $response->assertOk();
        $response->assertJsonPath('data.metrics.contract-management.0.metric_type', 'contracts_total');
    }

    public function test_admin_can_view_diagnostics(): void
    {
        $this->fakeAuth('Admin');

        DiagnosticInsight::create([
            'metric_type'     => 'risk_flag_rate',
            'period_start'    => now()->subDays(30)->toDateString(),
            'period_end'      => now()->toDateString(),
            'finding_summary' => ['current_period_avg' => 55.0],
            'ai_narrative'    => 'Test narrative.',
            'generated_at'    => now(),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/analytics/diagnostics');

        $response->assertOk();
        $response->assertJsonPath('data.0.metric_type', 'risk_flag_rate');
        $response->assertJsonPath('data.0.ai_narrative', 'Test narrative.');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/analytics/summary');
        $response->assertStatus(401);
    }
}
