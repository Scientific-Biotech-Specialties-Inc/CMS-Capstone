<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 4 (Analytics): covers the internal metrics snapshot endpoint used
 * by analytics-service's descriptive aggregation.
 */
class InternalMetricsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function secretHeader(): array
    {
        return ['X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET', '')];
    }

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\DB::table('contract_regions')->insertOrIgnore([
            ['region_name' => 'Luzon'],
        ]);
    }

    public function test_request_without_secret_is_rejected()
    {
        $response = $this->getJson('/api/internal/metrics/contracts');
        $response->assertStatus(401);
    }

    public function test_returns_contract_metrics_snapshot()
    {
        $cat = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        $approved = ContractApprovalStatus::firstOrCreate(['status_name' => 'Approved']);
        $regionId = \Illuminate\Support\Facades\DB::table('contract_regions')->first()->region_id;

        Contract::create([
            'category_id' => $cat->category_id,
            'approval_status_id' => $approved->approval_status_id,
            'bp_name' => 'Test Vendor',
            'item_code' => 'ITM-1',
            'description' => 'Desc',
            'serial_number' => 'SN-1',
            'sbu_number' => 'SBU-1',
            'region_id' => $regionId,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_by' => 1,
        ]);

        $response = $this->withHeaders($this->secretHeader())->getJson('/api/internal/metrics/contracts');

        $response->assertOk();
        $response->assertJsonPath('total_contracts', 1);
        $response->assertJsonStructure([
            'total_contracts', 'by_status', 'by_category', 'by_region',
            'expiring_soon_30d', 'expired', 'renewal_pipeline_by_month',
            'avg_approval_time_hours', 'high_risk_approvals_pending', 'high_risk_approvals_escalated',
        ]);
    }
}
