<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use App\Services\AuthService;
use App\Services\NotificationEventBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class NotificationStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_push_publishes_event_to_broker(): void
    {
        config(['services.internal_secret' => 'test-secret']);

        $broker = Mockery::mock(NotificationEventBroker::class);
        $broker->shouldReceive('publish')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['notification_type'] === 'manager_approval_request'
                    && $payload['target_roles'] === 'Manager'
                    && $payload['message'] === 'New contract awaits approval';
            })
            ->andReturn(true);

        $this->app->instance(NotificationEventBroker::class, $broker);

        $response = $this->withHeaders([
            'X-Internal-Secret' => 'test-secret',
        ])->postJson('/api/internal/push', [
            'contract_id'       => 10,
            'notification_type' => 'manager_approval_request',
            'target_roles'      => 'Manager',
            'message'           => 'New contract awaits approval',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('notifications', [
            'contract_id'       => 10,
            'notification_type' => 'manager_approval_request',
            'target_roles'      => 'Manager',
        ]);
    }

    public function test_stream_rejects_unauthenticated_requests(): void
    {
        $response = $this->get('/api/notifications/stream');
        $response->assertStatus(401);
    }

    public function test_stream_returns_sse_headers_and_filters_by_role_and_user(): void
    {
        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('verifyToken')
            ->once()
            ->with('valid-token')
            ->andReturn([
                'valid' => true,
                'user'  => [
                    'id'          => 7,
                    'role'        => 'Sales',
                    'permissions' => ['cms.contracts.view'],
                ],
            ]);
        $this->app->instance(AuthService::class, $authService);

        $broker = Mockery::mock(NotificationEventBroker::class);
        $broker->shouldReceive('subscribe')
            ->once()
            ->andReturnUsing(function (callable $onMessage): bool {
                // Should be filtered OUT (Manager-only)
                $onMessage([
                    'id'                => '1',
                    'contract_id'       => 1,
                    'notification_type' => 'manager_approval_request',
                    'message'           => 'Secret manager alert',
                    'notification_date' => '2026-11-27T00:00:00Z',
                    'target_roles'      => 'Manager',
                    'target_user_id'    => null,
                ]);
                // Should be DELIVERED (matches Sales user #7)
                $onMessage([
                    'id'                => '2',
                    'contract_id'       => 2,
                    'notification_type' => 'contract_status_updated',
                    'message'           => 'Your contract was approved',
                    'notification_date' => '2026-11-27T00:01:00Z',
                    'target_roles'      => 'Sales',
                    'target_user_id'    => 7,
                ]);
                return true;
            });
        $this->app->instance(NotificationEventBroker::class, $broker);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
        ])->get('/api/notifications/stream?duration=1');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('event: connected', $content);
        $this->assertStringContainsString('event: notification', $content);
        $this->assertStringContainsString('Your contract was approved', $content);
        $this->assertStringNotContainsString('Secret manager alert', $content);
    }
}
