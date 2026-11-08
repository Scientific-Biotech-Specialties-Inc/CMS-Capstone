<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EmailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private function mockAuthUser(int $id = 1, string $email = 'user@example.com', string $role = 'Sales'): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => $id,
                    'email' => $email,
                    'role' => $role,
                    'permissions' => []
                ]
            ], 200)
        ]);
    }

    public function test_it_returns_default_preferences_if_none_exist(): void
    {
        $this->mockAuthUser(100);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
            'Accept' => 'application/json',
        ])->getJson('/api/email-preferences');

        $response->assertStatus(200)
            ->assertJsonPath('data.user_id', 100)
            ->assertJsonPath('data.email_notifications_enabled', true)
            ->assertJsonPath('data.contract_expiry_alerts', true);

        $this->assertDatabaseHas('email_preferences', [
            'user_id' => 100,
            'email_notifications_enabled' => true,
            'contract_expiry_alerts' => true,
        ]);
    }

    public function test_it_updates_preferences(): void
    {
        $this->mockAuthUser(100);

        // Create an existing preference first
        EmailPreference::create([
            'user_id' => 100,
            'email_notifications_enabled' => true,
            'contract_expiry_alerts' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
            'Accept' => 'application/json',
        ])->putJson('/api/email-preferences', [
            'email_notifications_enabled' => false,
            'contract_expiry_alerts' => false,
            'system_alerts_enabled' => false,
            'sms_notifications_enabled' => true,
            'login_alerts_enabled' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user_id', 100)
            ->assertJsonPath('data.email_notifications_enabled', false)
            ->assertJsonPath('data.contract_expiry_alerts', false)
            ->assertJsonPath('data.system_alerts_enabled', false)
            ->assertJsonPath('data.sms_notifications_enabled', true)
            ->assertJsonPath('data.login_alerts_enabled', false);

        $this->assertDatabaseHas('email_preferences', [
            'user_id' => 100,
            'email_notifications_enabled' => false,
            'contract_expiry_alerts' => false,
            'system_alerts_enabled' => false,
            'sms_notifications_enabled' => true,
            'login_alerts_enabled' => false,
        ]);
    }

    public function test_it_validates_preferences_update(): void
    {
        $this->mockAuthUser(100);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
            'Accept' => 'application/json',
        ])->putJson('/api/email-preferences', [
            'email_notifications_enabled' => 'not-a-boolean',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email_notifications_enabled',
                'contract_expiry_alerts',
                'system_alerts_enabled',
                'sms_notifications_enabled',
                'login_alerts_enabled',
            ]);
    }
}
