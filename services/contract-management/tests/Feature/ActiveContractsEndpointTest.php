<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveContractsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function secretHeader(): array
    {
        return ['X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET', '')];
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('contract_regions')->insertOrIgnore([
            ['region_name' => 'Luzon'],
        ]);
    }

    public function test_active_contracts_requires_internal_secret(): void
    {
        $this->getJson('/api/active')->assertStatus(401);
        $this->getJson('/api/internal/contracts/active')->assertStatus(401);
    }

    public function test_returns_only_approved_non_expired_contracts_with_search_filter(): void
    {
        $cat = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        ContractApprovalStatus::firstOrCreate(['approval_status_id' => 1, 'status_name' => 'Pending']);
        ContractApprovalStatus::firstOrCreate(['approval_status_id' => 2, 'status_name' => 'Approved']);
        $regionId = DB::table('contract_regions')->first()->region_id;

        Contract::create([
            'category_id' => $cat->category_id,
            'approval_status_id' => 2,
            'bp_name' => 'Acme Biotech Corp',
            'item_code' => 'ITM-100',
            'description' => 'Active contract',
            'serial_number' => 'SN-100',
            'sbu_number' => 'SBU-100',
            'region_id' => $regionId,
            'start_date' => '2026-01-01',
            'end_date' => now()->addMonths(6)->toDateString(),
            'contract_value' => 250000.00,
            'created_by' => 1,
        ]);

        Contract::create([
            'category_id' => $cat->category_id,
            'approval_status_id' => 1,
            'bp_name' => 'Pending Partner',
            'item_code' => 'ITM-101',
            'description' => 'Pending contract',
            'serial_number' => 'SN-101',
            'sbu_number' => 'SBU-101',
            'region_id' => $regionId,
            'start_date' => '2026-01-01',
            'end_date' => now()->addMonths(6)->toDateString(),
            'contract_value' => 100000.00,
            'created_by' => 1,
        ]);

        $response = $this->withHeaders($this->secretHeader())->getJson('/api/active?search=Acme');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_name', 'Acme Biotech Corp')
            ->assertJsonPath('data.0.contract_value', 250000);
    }
}
