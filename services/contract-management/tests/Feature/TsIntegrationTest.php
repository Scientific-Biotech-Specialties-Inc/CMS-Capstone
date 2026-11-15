<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\ContractController;
use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use App\Models\ContractRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $tsKey = 'ts-test-secret-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ts.inbound_key' => $this->tsKey,
            'services.ts.internal_secret' => 'internal-test-secret',
            'services.ts.public_read' => false,
            'services.vendor.url' => 'http://vendor-management:8000/api',
            'services.vendor.secret' => 'internal-test-secret',
        ]);
    }

    public function test_ts_endpoints_reject_missing_or_invalid_secret(): void
    {
        $this->getJson('/api/integrations/v1/ts/sync')->assertStatus(403);
        $this->getJson('/api/integrations/v1/ts/equipment')->assertStatus(403);
        $this->getJson('/api/integrations/v1/ts/customers')->assertStatus(403);

        $this->withHeaders(['X-Internal-Secret' => 'wrong-secret'])
            ->getJson('/api/integrations/v1/ts/sync')
            ->assertStatus(403);
    }

    public function test_ts_sync_returns_approved_active_records_and_decrypted_vendor_fields(): void
    {
        $approvedContract = $this->seedContract([
            'bp_name' => 'St. Luke Medical Center',
            'serial_number' => 'SN-2026-0101',
            'item_code' => 'ITM-0101',
            'sbu_number' => 'SBU-001',
            'sbsi_branch' => 'Main Office',
            'equipment_name' => 'Sysmex XN-1000 Analyzer',
            'brand' => 'Sysmex',
            'model' => 'XN-1000',
            'approval_status' => 'Approved',
            'end_date' => now()->addYear()->toDateString(),
        ]);

        // Pending & expired should be excluded by default.
        $this->seedContract([
            'bp_name' => 'Pending Hospital',
            'serial_number' => 'SN-2026-0102',
            'sbsi_branch' => 'Cebu Branch',
            'equipment_name' => null,
            'brand' => 'SBSI Software',
            'model' => 'LIS-v2',
            'approval_status' => 'Pending',
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $this->seedContract([
            'bp_name' => 'Expired Clinic',
            'serial_number' => 'SN-2026-0103',
            'sbsi_branch' => 'Main Office',
            'equipment_name' => 'Old Machine',
            'brand' => 'Acme',
            'model' => 'X1',
            'approval_status' => 'Approved',
            'end_date' => now()->subDay()->toDateString(),
        ]);

        Http::fake([
            'http://vendor-management:8000/api/internal/partners*' => Http::response([
                'data' => [
                    [
                        'partner_id' => 15,
                        'bp_code' => 'BP-0015',
                        'partner_name' => 'St. Luke Medical Center',
                        'contact_person' => 'Dr. Maria Santos',
                        'email' => 'biomed@stluke.test',
                        'contact_number' => '+63-917-123-4567',
                        'address' => 'Quezon City',
                    ],
                ],
                'last_page' => 1,
            ], 200),
            'http://vendor-management:8000/api/internal/suppliers*' => Http::response([
                'data' => [],
                'last_page' => 1,
            ], 200),
        ]);

        $res = $this->withHeaders(['X-Internal-Secret' => $this->tsKey])
            ->getJson('/api/integrations/v1/ts/sync');

        $res->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.source_of_truth', 'CMS')
            ->assertJsonPath('data.0.equipment_uid', 'EQP-' . $approvedContract->contract_id)
            ->assertJsonPath('data.0.serial_number', 'SN-2026-0101')
            ->assertJsonPath('data.0.customer_uid', 'BP-0015')
            ->assertJsonPath('data.0.sbsi_branch', 'Main Office')
            ->assertJsonPath('data.0.equipment.equipment_name', 'Sysmex XN-1000 Analyzer')
            ->assertJsonPath('data.0.equipment.brand', 'Sysmex')
            ->assertJsonPath('data.0.equipment.model', 'XN-1000')
            ->assertJsonPath('data.0.equipment.serial_number', 'SN-2026-0101')
            ->assertJsonPath('data.0.customer.customer_name', 'St. Luke Medical Center')
            ->assertJsonPath('data.0.customer.customer_email', 'biomed@stluke.test')
            ->assertJsonPath('data.0.customer.contact_number', '+63-917-123-4567')
            ->assertJsonPath('data.0.customer.sbsi_branch', 'Main Office');
    }

    public function test_ts_sync_supports_bearer_auth_excludes_pending_and_supports_optional_equipment_name(): void
    {
        $this->seedContract([
            'bp_name' => 'Approved Clinic',
            'serial_number' => 'SN-2026-0201',
            'sbsi_branch' => 'Main Office',
            'equipment_name' => 'Analyzer Pro',
            'brand' => 'Roche',
            'model' => 'Cobas c311',
            'approval_status' => 'Approved',
        ]);

        $softwareContract = $this->seedContract([
            'bp_name' => 'Software Lab Inc',
            'serial_number' => 'SN-2026-0202',
            'sbsi_branch' => 'Davao Branch',
            'equipment_name' => null,
            'brand' => 'SBSI Software',
            'model' => 'Cloud-LIMS',
            'approval_status' => 'Approved',
        ]);

        $pendingContract = $this->seedContract([
            'bp_name' => 'Pending Lab Inc',
            'serial_number' => 'SN-2026-0203',
            'sbsi_branch' => 'Davao Branch',
            'equipment_name' => 'Unapproved Unit',
            'brand' => 'PendingBrand',
            'model' => 'P1',
            'approval_status' => 'Pending',
        ]);

        Http::fake([
            'http://vendor-management:8000/api/internal/partners*' => Http::response([
                'data' => [
                    [
                        'partner_id' => 16,
                        'bp_code' => 'BP-0016',
                        'partner_name' => 'Approved Clinic',
                        'email' => 'clinic@approved.example.com',
                    ],
                    [
                        'partner_id' => 17,
                        'bp_code' => 'BP-0017',
                        'partner_name' => 'Software Lab Inc',
                        'email' => 'support@softwarelab.example.com',
                    ],
                    [
                        'partner_id' => 18,
                        'bp_code' => 'BP-0018',
                        'partner_name' => 'Pending Lab Inc',
                        'email' => 'pending@lab.example.com',
                    ],
                ],
                'last_page' => 1,
            ], 200),
            'http://vendor-management:8000/api/internal/suppliers*' => Http::response(['data' => [], 'last_page' => 1], 200),
        ]);

        // Even with include_pending=true, Pending contracts must stay excluded.
        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tsKey])
            ->getJson('/api/integrations/v1/ts/equipment?include_pending=true&sbsi_branch=Davao%20Branch');

        $res->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.equipment_uid', 'EQP-' . $softwareContract->contract_id)
            ->assertJsonPath('data.0.equipment_name', null)
            ->assertJsonPath('data.0.brand', 'SBSI Software')
            ->assertJsonPath('data.0.model', 'Cloud-LIMS')
            ->assertJsonPath('data.0.sbsi_branch', 'Davao Branch')
            ->assertJsonMissing(['contract_id' => $pendingContract->contract_id]);

        // Also test TS customers feed deduplication & linkage.
        $customersRes = $this->withHeaders(['X-Internal-Secret' => $this->tsKey])
            ->getJson('/api/integrations/v1/ts/customers?include_pending=true&serial_number=SN-2026-0202');

        $customersRes->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_name', 'Software Lab Inc')
            ->assertJsonPath('data.0.sbsi_branch', 'Davao Branch')
            ->assertJsonPath('data.0.sbsi_branches.0', 'Davao Branch')
            ->assertJsonPath('data.0.contract_ids.0', $softwareContract->contract_id)
            ->assertJsonPath('data.0.equipment_uids.0', 'EQP-' . $softwareContract->contract_id)
            ->assertJsonPath('data.0.ordered_items.0.equipment_uid', 'EQP-' . $softwareContract->contract_id);
    }

    public function test_ts_only_exposes_business_partners_with_contracts_and_excludes_suppliers(): void
    {
        $partnerContract = $this->seedContract([
            'bp_name' => 'Contracted Hospital',
            'serial_number' => 'SN-2026-0401',
            'sbsi_branch' => 'Cebu Branch',
            'equipment_name' => 'Mindray BS-240',
            'brand' => 'Mindray',
            'model' => 'BS-240',
            'approval_status' => 'Approved',
        ]);

        // Contract belonging to a Supplier (not a Business Partner) must be excluded.
        $supplierId = 5;
        if (\Illuminate\Support\Facades\Schema::hasTable('suppliers')) {
            $supplierId = \Illuminate\Support\Facades\DB::table('suppliers')->insertGetId([
                'supplier_name' => 'Contracted Supplier Corp',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $supplierContract = $this->seedContract([
            'bp_name' => 'Contracted Supplier Corp',
            'serial_number' => 'SN-2026-0402',
            'sbsi_branch' => 'Main Office',
            'equipment_name' => 'Supply Analyzer',
            'brand' => 'Acme',
            'model' => 'S1',
            'approval_status' => 'Approved',
        ]);
        $supplierContract->supplier_id = $supplierId;
        $supplierContract->save();

        Http::fake([
            'http://vendor-management:8000/api/internal/partners*' => Http::response([
                'data' => [
                    [
                        'partner_id' => 21,
                        'bp_code' => 'BP-0021',
                        'partner_name' => 'Contracted Hospital',
                        'email' => 'procurement@contracted.example.com',
                        'contact_number' => '09170000001',
                        'contact_person' => 'Ana Reyes',
                        'address' => 'Cebu City',
                        'region' => 'Visayas',
                        'status' => 'Active',
                    ],
                    [
                        'partner_id' => 22,
                        'bp_code' => 'BP-0022',
                        'partner_name' => 'Standalone Clinic Without Contract',
                        'email' => 'hello@standalone.example.com',
                        'contact_number' => '09170000002',
                        'contact_person' => 'Ben Cruz',
                        'address' => 'Makati City',
                        'region' => 'Luzon',
                        'status' => 'Active',
                    ],
                ],
                'last_page' => 1,
            ], 200),
            'http://vendor-management:8000/api/internal/suppliers*' => Http::response([
                'data' => [
                    [
                        'supplier_id' => 5,
                        'supplier_name' => 'Contracted Supplier Corp',
                        'email' => 'sales@directmedical.example.com',
                    ],
                ],
                'last_page' => 1,
            ], 200),
        ]);

        foreach (['/api/integrations/v1/ts/customers', '/api/integrations/v1/ts/vendors', '/api/integrations/v1/ts/sync', '/api/integrations/v1/ts/equipment'] as $endpoint) {
            $this->withHeaders(['X-Internal-Secret' => $this->tsKey])
                ->getJson($endpoint)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('meta.total', 1);
        }

        $this->withHeaders(['X-Internal-Secret' => $this->tsKey])
            ->getJson('/api/integrations/v1/ts/customers')
            ->assertOk()
            ->assertJsonPath('data.0.customer_uid', 'BP-0021')
            ->assertJsonPath('data.0.customer_name', 'Contracted Hospital')
            ->assertJsonPath('data.0.customer_email', 'procurement@contracted.example.com')
            ->assertJsonPath('data.0.sbsi_branch', 'Cebu Branch')
            ->assertJsonPath('data.0.sbsi_branches.0', 'Cebu Branch')
            ->assertJsonPath('data.0.serial_numbers.0', 'SN-2026-0401')
            ->assertJsonPath('data.0.ordered_items.0.contract_id', $partnerContract->contract_id)
            ->assertJsonPath('data.0.ordered_items.0.serial_number', 'SN-2026-0401')
            ->assertJsonPath('data.0.ordered_items.0.equipment_name', 'Mindray BS-240');
    }

    public function test_lookups_branches_fetches_from_auth_service_with_safe_fallback(): void
    {
        Http::fake([
            '*/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => ['id' => 1, 'role' => 'Admin'],
            ], 200),
            '*/internal/branches' => Http::response([
                'valid' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Main Office', 'address' => 'Makati City'],
                    ['id' => 2, 'name' => 'Cebu Branch', 'address' => 'Cebu City'],
                ],
            ], 200),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer mock-jwt'])
            ->getJson('/api/lookups/branches')
            ->assertOk()
            ->assertJsonPath('data', ['Main Office', 'Cebu Branch']);
    }

    public function test_lookups_branches_falls_back_to_main_office_when_auth_service_fails(): void
    {
        Http::fake([
            '*/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => ['id' => 1, 'role' => 'Admin'],
            ], 200),
            '*/internal/branches' => Http::response([], 500),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer mock-jwt'])
            ->getJson('/api/lookups/branches')
            ->assertOk()
            ->assertJsonPath('data', ['Main Office']);
    }

    public function test_format_contract_exposes_ts_fields(): void
    {
        $contract = $this->seedContract([
            'bp_name' => 'Beta Diagnostics',
            'serial_number' => 'SN-2026-0301',
            'sbsi_branch' => 'Main Office',
            'equipment_name' => 'Mindray BC-5000',
            'brand' => 'Mindray',
            'model' => 'BC-5000',
            'approval_status' => 'Approved',
        ]);

        $formatted = ContractController::formatContract($contract->load(['category', 'approvalStatus', 'workflowStatus', 'region']));

        $this->assertSame('Main Office', $formatted['sbsi_branch']);
        $this->assertSame('Mindray BC-5000', $formatted['equipment_name']);
        $this->assertSame('Mindray', $formatted['brand']);
        $this->assertSame('BC-5000', $formatted['model']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedContract(array $overrides = []): Contract
    {
        $category = ContractCategory::firstOrCreate(['category_name' => 'Equipment Lease']);
        $approvalStatus = ContractApprovalStatus::firstOrCreate([
            'status_name' => $overrides['approval_status'] ?? 'Approved',
        ]);
        $region = ContractRegion::firstOrCreate(['region_name' => 'Luzon']);

        return Contract::create([
            'category_id' => $category->category_id,
            'approval_status_id' => $approvalStatus->approval_status_id,
            'region_id' => $region->region_id,
            'bp_name' => $overrides['bp_name'] ?? 'Test Partner',
            'item_code' => $overrides['item_code'] ?? 'ITM-0001',
            'description' => 'Service and equipment contract',
            'serial_number' => $overrides['serial_number'] ?? ('SN-2026-' . random_int(1000, 9999)),
            'sbu_number' => $overrides['sbu_number'] ?? 'SBU-001',
            'sbsi_branch' => $overrides['sbsi_branch'] ?? 'Main Office',
            'equipment_name' => $overrides['equipment_name'] ?? null,
            'brand' => $overrides['brand'] ?? 'Generic',
            'model' => $overrides['model'] ?? 'G-100',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => $overrides['end_date'] ?? now()->addYear()->toDateString(),
            'created_by' => 1,
        ]);
    }
}
