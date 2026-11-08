<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessPartner;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\VendorContractAssociation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IntegrationCustomerTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'cms-internal-secret-key-2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = (string) (env('INTERNAL_SERVICE_SECRET') ?: 'cms-internal-secret-key-2026');
        // Set up the environment secret key
        config(['services.auth.secret' => $this->secret]);
        putenv("INTERNAL_SERVICE_SECRET={$this->secret}");
    }

    public function test_forbidden_if_secret_header_is_missing(): void
    {
        $response = $this->getJson('/api/integration/customers');

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Forbidden. Invalid or missing integration secret.',
        ]);
    }

    public function test_forbidden_if_secret_header_is_incorrect(): void
    {
        $response = $this->withHeaders([
            'X-Internal-Secret' => 'wrong-secret-key',
        ])->getJson('/api/integration/customers');

        $response->assertStatus(403);
    }

    public function test_returns_only_active_partners_with_contracts(): void
    {
        // 1. Active partner with contract (Should be returned)
        $partner1 = BusinessPartner::create([
            'bp_code' => 'BP-001',
            'partner_name' => 'Active Partner with Contract',
            'email' => 'partner1@example.com',
            'status' => 'Active',
        ]);
        $contract1 = Contract::create([
            'contract_id' => 201,
            'description' => 'Contract 1',
        ]);
        VendorContractAssociation::create([
            'vendor_type' => 'partner',
            'vendor_id' => $partner1->partner_id,
            'contract_id' => $contract1->contract_id,
        ]);

        // 2. Inactive partner with contract (Should be filtered out)
        $partner2 = BusinessPartner::create([
            'bp_code' => 'BP-002',
            'partner_name' => 'Inactive Partner with Contract',
            'email' => 'partner2@example.com',
            'status' => 'Inactive',
        ]);
        $contract2 = Contract::create([
            'contract_id' => 202,
            'description' => 'Contract 2',
        ]);
        VendorContractAssociation::create([
            'vendor_type' => 'partner',
            'vendor_id' => $partner2->partner_id,
            'contract_id' => $contract2->contract_id,
        ]);

        // 3. Active partner without contract (Should be filtered out)
        $partner3 = BusinessPartner::create([
            'bp_code' => 'BP-003',
            'partner_name' => 'Active Partner without Contract',
            'email' => 'partner3@example.com',
            'status' => 'Active',
        ]);

        // 4. Supplier with contract (Should be filtered out)
        $supplier = Supplier::create([
            'supplier_name' => 'Active Supplier with Contract',
            'email' => 'supplier@example.com',
            'status' => 'Active',
        ]);
        $contract3 = Contract::create([
            'contract_id' => 203,
            'description' => 'Contract 3',
        ]);
        VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $contract3->contract_id,
        ]);

        $response = $this->withHeaders([
            'X-Internal-Secret' => $this->secret,
        ])->getJson('/api/integration/customers');

        $response->assertStatus(200);
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.partner_name', 'Active Partner with Contract');
        $response->assertJsonPath('data.0.bp_code', 'BP-001');
    }

    public function test_pagination_parameters_are_respected(): void
    {
        // Create 3 active partners with contracts
        for ($i = 1; $i <= 3; $i++) {
            $partner = BusinessPartner::create([
                'bp_code' => "BP-00{$i}",
                'partner_name' => "Partner {$i}",
                'email' => "partner{$i}@example.com",
                'status' => 'Active',
            ]);
            $contract = Contract::create([
                'contract_id' => 300 + $i,
                'description' => "Contract {$i}",
            ]);
            VendorContractAssociation::create([
                'vendor_type' => 'partner',
                'vendor_id' => $partner->partner_id,
                'contract_id' => $contract->contract_id,
            ]);
        }

        // Test per_page = 2
        $response = $this->withHeaders([
            'X-Internal-Secret' => $this->secret,
        ])->getJson('/api/integration/customers?per_page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('per_page', 2);
        $response->assertJsonPath('total', 3);
        $response->assertJsonCount(2, 'data');

        // Test page = 2
        $response2 = $this->withHeaders([
            'X-Internal-Secret' => $this->secret,
        ])->getJson('/api/integration/customers?per_page=2&page=2');

        $response2->assertStatus(200);
        $response2->assertJsonCount(1, 'data');
    }
}
