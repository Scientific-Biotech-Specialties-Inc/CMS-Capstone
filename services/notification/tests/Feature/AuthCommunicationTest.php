<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_communicates_with_auth_service_for_notifications()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'user@example.com',
                    'role' => 'Sales',
                    'permissions' => []
                ]
            ], 200)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer user-token',
            'Accept' => 'application/json',
        ])->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_it_filters_notifications_when_system_alerts_are_disabled()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'user@example.com',
                    'role' => 'Sales',
                    'permissions' => []
                ]
            ], 200)
        ]);

        // Disable system alerts
        \App\Models\EmailPreference::create([
            'user_id' => 1,
            'email_notifications_enabled' => true,
            'contract_expiry_alerts' => true,
            'system_alerts_enabled' => false,
            'sms_notifications_enabled' => false,
            'login_alerts_enabled' => true,
        ]);

        // Push a notification
        \App\Models\Notification::create([
            'contract_id' => 1,
            'notification_type' => 'expiry_30',
            'target_roles' => 'Sales',
            'message' => 'Your contract is expiring.',
            'notification_date' => now(),
            'is_read' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer user-token',
            'Accept' => 'application/json',
        ])->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertExactJson(['data' => []]);
    }

    public function test_it_denies_notifications_with_invalid_token()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => false
            ], 401)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer bad-token',
            'Accept' => 'application/json',
        ])->getJson('/api/notifications');

        $response->assertStatus(401);
    }
}
