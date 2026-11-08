<?php

namespace Tests\Feature;

use App\Models\BusinessPartner;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BusinessPartnerCrudTest extends TestCase
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

    public function test_admin_can_create_partner()
    {
        $this->mockAuthService();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson('/api/partners', [
            'bp_code' => 'BP-999',
            'partner_name' => 'Acme Partner',
            'contact_number' => '12345678',
            'email' => 'acme_partner@test.com',
            'address' => '123 Acme St',
            'region' => 'Metro Manila'
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.partner_name', 'Acme Partner');

        $this->assertDatabaseHas('business_partners', [
            'partner_name' => 'Acme Partner',
            'bp_code' => 'BP-999'
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'entity_type' => 'BusinessPartner',
            'user_id' => 10
        ]);
    }

    public function test_non_admin_cannot_create_partner()
    {
        // Mock non-admin who does not have manage-partners permission
        $this->mockAuthService('Employee', ['cms.partners.view']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson('/api/partners', [
            'partner_name' => 'Acme Partner',
            'bp_code' => 'BP-999'
        ]);

        $response->assertStatus(403);
    }

    public function test_duplicate_bp_code_blocked_on_create()
    {
        $this->mockAuthService();

        BusinessPartner::create([
            'bp_code' => 'BP-999',
            'partner_name' => 'Acme Original'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson('/api/partners', [
            'bp_code' => 'BP-999',
            'partner_name' => 'Acme Duplicate',
            'email' => 'acme_dup@test.com',
        ]);

        $response->assertStatus(409); // Conflict
        $response->assertJsonFragment([
            'message' => 'A business partner with this BP code already exists.'
        ]);
    }

    public function test_fuzzy_name_returns_warning_not_blocked()
    {
        $this->mockAuthService();

        BusinessPartner::create([
            'bp_code' => 'BP-999',
            'partner_name' => 'Acme Corporation'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson('/api/partners', [
            'bp_code' => 'BP-888',
            'partner_name' => 'Acme Corp',
            'email' => 'acme_corp@test.com',
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

    public function test_admin_can_update_partner()
    {
        $this->mockAuthService();

        $partner = BusinessPartner::create([
            'bp_code' => 'BP-999',
            'partner_name' => 'Old Name'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->putJson('/api/partners/' . $partner->partner_id, [
            'bp_code' => 'BP-999', // same BP code is allowed for self
            'partner_name' => 'New Name',
            'email' => 'updated_partner@test.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.partner_name', 'New Name');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'entity_type' => 'BusinessPartner',
            'entity_id' => $partner->partner_id
        ]);
    }

    public function test_admin_can_delete_partner()
    {
        $this->mockAuthService();

        $partner = BusinessPartner::create([
            'bp_code' => 'BP-999',
            'partner_name' => 'Delete Me'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->deleteJson('/api/partners/' . $partner->partner_id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('business_partners', [
            'partner_id' => $partner->partner_id
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'entity_type' => 'BusinessPartner',
            'entity_id' => $partner->partner_id
        ]);
    }

    public function test_search_returns_results_within_2_seconds()
    {
        $startTime = microtime(true);

        $this->mockAuthService();

        BusinessPartner::create([
            'bp_code' => 'BP-999',
            'partner_name' => 'Acme Corporation'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->getJson('/api/partners/search?q=Acme');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.partner_name', 'Acme Corporation');

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

        $response = $this->postJson('/api/partners', [
            'partner_name' => 'Acme Corp'
        ]);

        $response->assertStatus(401);
    }
}
