<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\AuditLog;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::store('file')->flush();
    }

    /**
     * Test webhook receiver with correct secret.
     */
    public function test_internal_webhook_receive_success()
    {
        config(['app.internal_service_secret' => 'cms-internal-secret-key-2026']);
        putenv('INTERNAL_SERVICE_SECRET=cms-internal-secret-key-2026');

        $response = $this->withHeaders([
            'X-Internal-Secret' => 'cms-internal-secret-key-2026'
        ])->postJson('/api/internal/audit-event', [
            'action' => 'login',
            'entity_type' => 'Session',
            'user_id' => 3,
            'new_data' => ['email' => 'finance-user@sbsi.com'],
            'user_department' => 'Sales & Marketing'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'entity_type' => 'Session',
            'user_id' => 3,
        ]);
    }

    /**
     * Test webhook receiver with unauthorized secret.
     */
    public function test_internal_webhook_unauthorized_secret()
    {
        config(['app.internal_service_secret' => 'cms-internal-secret-key-2026']);
        putenv('INTERNAL_SERVICE_SECRET=cms-internal-secret-key-2026');

        $response = $this->withHeaders([
            'X-Internal-Secret' => 'wrong-secret'
        ])->postJson('/api/internal/audit-event', [
            'action' => 'login',
            'entity_type' => 'Session',
            'user_id' => 3,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test log aggregator endpoint.
     */
    public function test_audit_log_aggregator_success()
    {
        config(['app.internal_service_secret' => 'cms-internal-secret-key-2026']);
        putenv('INTERNAL_SERVICE_SECRET=cms-internal-secret-key-2026');

        // 1. Seed two local audit logs: one contract event and one login event
        AuditLog::create([
            'action' => 'created',
            'entity_type' => 'Contract',
            'entity_id' => 12,
            'user_id' => 5,
            'user_name' => 'Jane Doe',
            'user_email' => 'contractor@sbsi.com',
            'user_role' => 'Manager',
            'user_department' => 'Sales & Marketing',
            'old_data' => null,
            'new_data' => ['title' => 'Important NDA'],
            'performed_at' => now(),
        ]);

        AuditLog::create([
            'action' => 'Login Success',
            'entity_type' => 'Session',
            'entity_id' => 0,
            'user_id' => 5,
            'user_name' => 'Jane Doe',
            'user_email' => 'contractor@sbsi.com',
            'user_role' => 'Manager',
            'user_department' => 'Sales & Marketing',
            'old_data' => null,
            'new_data' => ['email' => 'contractor@sbsi.com'],
            'performed_at' => now(),
        ]);

        // 2. Mock only the user-mapping call (Step 1 fallback), not the removed remote audit log call
        Http::fake([
            // Mock verify token for aggregator accessor (needs manage-users permission)
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'admin@sbsi.com',
                    'role' => 'Admin',
                    'permissions' => ['cms.users.view'],
                    'department' => 'Sales & Marketing'
                ]
            ], 200),
            // Mock auth users mapping API (fallback for user name resolution)
            'http://auth-service:8000/api/admin/users?per_page=100' => Http::response([
                'data' => [
                    [
                        'id' => 5,
                        'email' => 'contractor@sbsi.com',
                        'profile' => [
                            'first_name' => 'Jane',
                            'last_name' => 'Doe',
                            'role' => ['name' => 'Manager']
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer admin-token',
            'Accept' => 'application/json'
        ])->getJson('/api/audit-logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data', 'total', 'current_page', 'per_page', 'last_page'
        ]);

        // Verify we have our two seeded local logs
        $data = $response->json()['data'];
        $this->assertGreaterThanOrEqual(2, count($data));

        // All entries must be from the local CMS store — no 'auth' source rows
        $sources = collect($data)->pluck('source')->unique()->toArray();
        $this->assertContains('cms', $sources);
        $this->assertNotContains('auth', $sources);

        // Verify Login Success entry is present
        $actions = collect($data)->pluck('action')->toArray();
        $this->assertContains('Login Success', $actions);
    }

    /**
     * OWASP A09: Non-Sales department events (Admin, Management) must be logged to the security channel.
     */
    public function test_non_sales_events_logged_to_security_channel()
    {
        $logged = false;
        \Illuminate\Support\Facades\Log::listen(function ($log) use (&$logged) {
            if ($log->message === 'Privileged action audit event' && ($log->context['action'] ?? '') === 'user_promoted') {
                $logged = true;
            }
        });

        $service = app(\App\Services\AuditLogService::class);
        $service->log('user_promoted', 'User', '10', 1, [], ['role' => 'Admin'], 'Management');

        $this->assertTrue($logged, 'Expected privileged action to be logged to security log channel');

        // Ensure it did not create a database audit log entry
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'user_promoted',
            'entity_id' => '10',
        ]);
    }
}
