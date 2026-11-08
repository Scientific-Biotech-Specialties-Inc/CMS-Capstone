<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendContractExpiryEmail;
use App\Models\EmailPreference;
use App\Models\EmailSendLog;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class EmailDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function getInternalHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Internal-Secret' => (string) (env('INTERNAL_SERVICE_SECRET') ?: 'cms-internal-secret-key-2026'),
        ];
    }

    public function test_it_dispatches_user_scoped_job_on_push_notification(): void
    {
        Bus::fake();

        $response = $this->withHeaders($this->getInternalHeaders())
            ->postJson('/api/internal/push', [
                'contract_id' => 10,
                'notification_type' => 'expiry_30',
                'target_roles' => 'Sales',
                'message' => 'Test message',
                'target_user_id' => 99,
            ]);

        $response->assertStatus(201);

        Bus::assertDispatched(SendContractExpiryEmail::class, function ($job) {
            return $job->userId === 99
                && $job->contractId === 10
                && $job->notificationType === 'expiry_30';
        });
    }

    public function test_job_sends_email_and_logs_when_opted_in(): void
    {
        Mail::fake();

        // Mock AuthService lookup and Contract Audit endpoint
        Http::fake([
            'http://auth-service:8000/api/internal/users/99' => Http::response([
                'id' => 99,
                'email' => 'sales@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'role' => 'Sales',
                'department' => 'Sales',
            ], 200),
            'http://contract-management:8000/api/internal/audit-event' => Http::response(['ok' => true], 200),
        ]);

        $notification = Notification::create([
            'contract_id' => 10,
            'user_id' => 99,
            'message' => 'Test message',
            'notification_type' => 'expiry_30',
            'target_roles' => 'Sales',
            'target_user_id' => 99,
        ]);

        // Preference defaults to enabled
        $job = new SendContractExpiryEmail(
            99,
            (int) $notification->notification_id,
            10,
            'Test message',
            'expiry_30'
        );

        $job->handle(new \App\Services\AuthService());

        Mail::assertSent(\App\Mail\ContractExpiryMail::class, function ($mail) {
            return $mail->hasTo('sales@example.com')
                && $mail->recipientName === 'John Doe'
                && $mail->messageText === 'Test message';
        });

        $this->assertDatabaseHas('email_send_logs', [
            'notification_id' => $notification->notification_id,
            'user_id' => 99,
            'status' => 'sent',
        ]);
    }

    public function test_job_skips_and_logs_skipped_when_opted_out(): void
    {
        Mail::fake();

        Http::fake([
            'http://auth-service:8000/api/internal/users/99' => Http::response([
                'id' => 99,
                'email' => 'sales@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'role' => 'Sales',
                'department' => 'Sales',
            ], 200),
            'http://contract-management:8000/api/internal/audit-event' => Http::response(['ok' => true], 200),
        ]);

        EmailPreference::create([
            'user_id' => 99,
            'email_notifications_enabled' => false,
        ]);

        $notification = Notification::create([
            'contract_id' => 10,
            'user_id' => 99,
            'message' => 'Test message',
            'notification_type' => 'expiry_30',
            'target_roles' => 'Sales',
            'target_user_id' => 99,
        ]);

        $job = new SendContractExpiryEmail(
            99,
            (int) $notification->notification_id,
            10,
            'Test message',
            'expiry_30'
        );

        $job->handle(new \App\Services\AuthService());

        Mail::assertNotSent(\App\Mail\ContractExpiryMail::class);

        $this->assertDatabaseMissing('email_send_logs', [
            'notification_id' => $notification->notification_id,
            'user_id' => 99,
        ]);
    }

    public function test_it_returns_email_logs_for_authenticated_user(): void
    {
        // Mock auth user
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 99,
                    'email' => 'sales@example.com',
                    'role' => 'Sales',
                    'permissions' => []
                ]
            ], 200)
        ]);

        EmailSendLog::create([
            'notification_id' => 1,
            'user_id' => 99,
            'recipient_email' => 'sales@example.com',
            'subject' => 'Test Subject',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Create log for a different user
        EmailSendLog::create([
            'notification_id' => 2,
            'user_id' => 100,
            'recipient_email' => 'other@example.com',
            'subject' => 'Other Subject',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token',
            'Accept' => 'application/json',
        ])->getJson('/api/email-logs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_email', 'sales@example.com');
    }

    public function test_job_skips_when_broadcast_role_but_not_sales_marketing_department(): void
    {
        Mail::fake();

        Http::fake([
            'http://auth-service:8000/api/internal/users/99' => Http::response([
                'id' => 99,
                'email' => 'manager@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'role' => 'Manager',
                'department' => 'Operations',
            ], 200),
            'http://contract-management:8000/api/internal/audit-event' => Http::response(['ok' => true], 200),
        ]);

        $notification = Notification::create([
            'contract_id' => 10,
            'user_id' => 99,
            'message' => 'Test message',
            'notification_type' => 'expiry_30',
            'target_roles' => 'Manager',
            'target_user_id' => 99,
        ]);

        $job = new SendContractExpiryEmail(
            99,
            (int) $notification->notification_id,
            10,
            'Test message',
            'expiry_30'
        );

        $job->handle(new \App\Services\AuthService());

        Mail::assertNotSent(\App\Mail\ContractExpiryMail::class);

        $this->assertDatabaseMissing('email_send_logs', [
            'notification_id' => $notification->notification_id,
            'user_id' => 99,
        ]);
    }
}
