<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessPartner;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\VendorContractAssociation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class VendorContractEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function mockAuthService(
        string $role = 'Manager',
        array $permissions = ['cms.partners.view', 'cms.partners.create', 'cms.partners.edit', 'cms.partners.delete'],
        int $userId = 10
    ): void {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => $userId,
                    'role' => $role,
                    'permissions' => $permissions,
                    'department' => 'Sales & Marketing'
                ]
            ]),
        ]);
    }

    public function test_manager_can_attach_contract_to_supplier(): void
    {
        $this->mockAuthService();

        $contract = Contract::create([
            'contract_id' => 101,
            'description' => 'Supplier Contract 101',
        ]);

        $supplier = Supplier::create([
            'supplier_name' => 'Supplier ABC',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
        ])->postJson("/api/suppliers/{$supplier->supplier_id}/contracts", [
            'contract_id' => $contract->contract_id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.contract_id', $contract->contract_id);
        $response->assertJsonPath('data.vendor_type', 'supplier');
        $response->assertJsonPath('data.attached_by', 10);

        $this->assertDatabaseHas('vendor_contract_associations', [
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $contract->contract_id,
            'attached_by' => 10,
        ]);
    }

    public function test_manager_can_attach_contract_to_partner(): void
    {
        $this->mockAuthService();

        $contract = Contract::create([
            'contract_id' => 102,
            'description' => 'Partner Contract 102',
        ]);

        $partner = BusinessPartner::create([
            'bp_code' => 'BP-102',
            'partner_name' => 'Partner ABC',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
        ])->postJson("/api/partners/{$partner->partner_id}/contracts", [
            'contract_id' => $contract->contract_id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.contract_id', $contract->contract_id);
        $response->assertJsonPath('data.vendor_type', 'partner');

        $this->assertDatabaseHas('vendor_contract_associations', [
            'vendor_type' => 'partner',
            'vendor_id' => $partner->partner_id,
            'contract_id' => $contract->contract_id,
        ]);
    }

    public function test_duplicate_attachment_returns_conflict_409(): void
    {
        $this->mockAuthService();

        $contract = Contract::create(['contract_id' => 103]);
        $supplier = Supplier::create(['supplier_name' => 'Sup 103']);

        VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $contract->contract_id,
            'attached_by' => 10,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
        ])->postJson("/api/suppliers/{$supplier->supplier_id}/contracts", [
            'contract_id' => $contract->contract_id,
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment([
            'message' => 'This contract is already linked to another vendor.',
        ]);
    }

    public function test_attaching_non_existent_contract_returns_validation_error_422(): void
    {
        $this->mockAuthService();
        $supplier = Supplier::create(['supplier_name' => 'Sup 104']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
        ])->postJson("/api/suppliers/{$supplier->supplier_id}/contracts", [
            'contract_id' => 9999, // non-existent
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['contract_id']);
    }

    public function test_manager_can_detach_contract(): void
    {
        $this->mockAuthService();

        $contract = Contract::create(['contract_id' => 105]);
        $supplier = Supplier::create(['supplier_name' => 'Sup 105']);

        VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $contract->contract_id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
        ])->deleteJson("/api/suppliers/{$supplier->supplier_id}/contracts/{$contract->contract_id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('vendor_contract_associations', [
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $contract->contract_id,
        ]);
    }

    public function test_detaching_non_existent_association_returns_404(): void
    {
        $this->mockAuthService();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
        ])->deleteJson("/api/suppliers/1/contracts/999");

        $response->assertStatus(404);
    }

    public function test_sales_user_index_returns_only_own_associations(): void
    {
        // Sales user 1 (ID: 15)
        $this->mockAuthService('Sales', ['cms.partners.view'], 15);

        $c1 = Contract::create(['contract_id' => 201]);
        $c2 = Contract::create(['contract_id' => 202]);
        $supplier = Supplier::create(['supplier_name' => 'Sup']);

        VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $c1->contract_id,
            'attached_by' => 15, // Sales user 1
        ]);

        VendorContractAssociation::create([
            'vendor_type' => 'supplier',
            'vendor_id' => $supplier->supplier_id,
            'contract_id' => $c2->contract_id,
            'attached_by' => 16, // Sales user 2
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
        ])->getJson("/api/suppliers/{$supplier->supplier_id}/contracts");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.contract_id', $c1->contract_id);
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => false,
            ]),
        ]);

        $response = $this->postJson("/api/suppliers/1/contracts", [
            'contract_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_edit_permission_is_rejected_with_403_on_write(): void
    {
        $this->mockAuthService('Sales', ['cms.partners.view'], 15);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
        ])->postJson("/api/suppliers/1/contracts", [
            'contract_id' => 1,
        ]);

        $response->assertStatus(403);
    }
}
