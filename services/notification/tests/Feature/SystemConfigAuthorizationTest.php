<?php

namespace Tests\Feature;

use App\Models\SystemConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemConfigAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAuth(string $role): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'user@sbsi.com',
                    'role' => $role,
                    'permissions' => []
                ]
            ], 200)
        ]);
    }

    public function test_sales_and_manager_cannot_update_system_config(): void
    {
        // 1. Sales role -> 403
        $this->fakeAuth('Sales');
        $resSales = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->putJson('/api/system-config', [
                'email_notifs_enabled' => false,
            ]);
        $resSales->assertStatus(403);
        $resSales->assertJson(['message' => 'Forbidden.']);

        // 2. Manager role -> 403
        $this->fakeAuth('Manager');
        $resManager = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->putJson('/api/system-config', [
                'email_notifs_enabled' => false,
            ]);
        $resManager->assertStatus(403);
    }

    public function test_admin_can_update_system_config(): void
    {
        $this->fakeAuth('Admin');

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->putJson('/api/system-config', [
                'email_notifs_enabled' => true,
                'in_app_notifs_enabled' => true,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('system_configurations', [
            'email_notifs_enabled' => true,
            'in_app_notifs_enabled' => true,
        ]);
    }
}
