<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function mockAuthService(string $role = 'Admin', array $permissions = ['cms.partners.view', 'cms.partners.create', 'cms.partners.edit', 'cms.partners.delete'])
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 10,
                    'role' => $role,
                    'permissions' => $permissions,
                    'department' => 'Sales & Marketing'
                ]
            ]),
        ]);
    }

    public function test_admin_can_create_supplier()
    {
        $this->mockAuthService();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson('/api/suppliers', [
            'supplier_name' => 'Acme Corp',
            'tin_number' => '111-222-333',
            'contact_number' => '12345678',
            'email' => 'acme@test.com',
            'address' => '123 Acme St',
            'region' => 'Metro Manila'
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.supplier_name', 'Acme Corp');

        $this->assertDatabaseHas('suppliers', [
            'supplier_name' => 'Acme Corp',
            'tin_number' => '111-222-333'
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'entity_type' => 'Supplier',
            'user_id' => 10
        ]);
    }

    public function test_non_admin_cannot_create_supplier()
    {
        // Mock non-admin who does not have manage-suppliers permission
        $this->mockAuthService('Employee', ['cms.partners.view']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson('/api/suppliers', [
            'supplier_name' => 'Acme Corp',
            'tin_number' => '111-222-333'
        ]);

        $response->assertStatus(403);
    }

    public function test_duplicate_tin_blocked_on_create()
    {
        $this->mockAuthService();

        Supplier::create([
            'supplier_name' => 'Acme Original',
            'tin_number' => '111-222-333'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson('/api/suppliers', [
            'supplier_name' => 'Acme Duplicate',
            'tin_number' => '111-222-333'
        ]);

        $response->assertStatus(409); // Conflict
        $response->assertJsonFragment([
            'message' => 'A supplier with this TIN number already exists.'
        ]);
    }

    public function test_fuzzy_name_returns_warning_not_blocked()
    {
        $this->mockAuthService();

        Supplier::create([
            'supplier_name' => 'Acme Corporation',
            'tin_number' => '111-222-333'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson('/api/suppliers', [
            'supplier_name' => 'Acme Corp',
            'tin_number' => '444-555-666'
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data',
            'warnings' => [
                '*' => [
                    'type',
                    'message',
                    'matches'
                ]
            ]
        ]);
        $this->assertEquals('fuzzy_name_match', $response->json('warnings.0.type'));
    }

    public function test_admin_can_update_supplier()
    {
        $this->mockAuthService();

        $supplier = Supplier::create([
            'supplier_name' => 'Old Name',
            'tin_number' => '111-222-333'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->putJson('/api/suppliers/' . $supplier->supplier_id, [
            'supplier_name' => 'New Name',
            'tin_number' => '111-222-333' // same TIN is allowed for self
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.supplier_name', 'New Name');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'entity_type' => 'Supplier',
            'entity_id' => $supplier->supplier_id
        ]);
    }

    public function test_admin_can_delete_supplier()
    {
        $this->mockAuthService();

        $supplier = Supplier::create([
            'supplier_name' => 'Delete Me',
            'tin_number' => '111-222-333'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->deleteJson('/api/suppliers/' . $supplier->supplier_id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('suppliers', [
            'supplier_id' => $supplier->supplier_id
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'entity_type' => 'Supplier',
            'entity_id' => $supplier->supplier_id
        ]);
    }

    public function test_search_returns_results_within_2_seconds()
    {
        $startTime = microtime(true);

        $this->mockAuthService();

        Supplier::create([
            'supplier_name' => 'Acme Corporation',
            'tin_number' => '111-222-333'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->getJson('/api/suppliers/search?q=Acme');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.supplier_name', 'Acme Corporation');

        $duration = microtime(true) - $startTime;
        $this->assertLessThan(2.0, $duration, "Search took longer than 2 seconds.");
    }

    public function test_unauthenticated_request_rejected()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => false
            ]),
        ]);

        $response = $this->postJson('/api/suppliers', [
            'supplier_name' => 'Acme Corp'
        ]);

        $response->assertStatus(401);
    }
}
