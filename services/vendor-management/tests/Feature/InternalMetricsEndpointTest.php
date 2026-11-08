<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 4 (Analytics): covers the internal vendor metrics snapshot
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
        $response = $this->getJson('/api/internal/metrics/vendors');
        $response->assertStatus(401);
    }

    public function test_returns_vendor_metrics_snapshot()
    {
        \Illuminate\Support\Facades\DB::table('suppliers')->insert([
            'supplier_name' => 'Test Supplier', 'tin_number' => '111-111-111',
            'region' => 'Luzon', 'industry' => 'Diagnostics', 'status' => 'Active', 'created_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('business_partners')->insert([
            'bp_code' => 'BP-1', 'partner_name' => 'Test Partner', 'email' => 'a@b.com',
            'region' => 'Visayas', 'industry' => 'Logistics', 'status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeaders($this->secretHeader())->getJson('/api/internal/metrics/vendors');

        $response->assertOk();
        $response->assertJsonPath('total_suppliers', 1);
        $response->assertJsonPath('total_partners', 1);
    }
}
