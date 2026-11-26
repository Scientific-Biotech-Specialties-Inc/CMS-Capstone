<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Models\Notification;
use App\Models\NotificationRead;
use App\Services\NotificationAudienceMatcher;
use App\Services\NotificationEventBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StreamController
{
    public function __invoke(
        Request $request,
        NotificationEventBroker $broker,
        NotificationAudienceMatcher $matcher
    ): StreamedResponse {
        $userId = (int) $request->input('auth_id');
        $userRole = (string) $request->input('auth_role');
        $duration = max(1, min((int) $request->query('duration', 25), 55));
        $sinceParam = $request->header('Last-Event-ID') ?: $request->query('since');

        return new StreamedResponse(function () use ($broker, $matcher, $userId, $userRole, $duration, $sinceParam): void {
            @set_time_limit(0);
            @ignore_user_abort(false);

            $this->emitEvent('connected', ['status' => 'ok', 'timestamp' => now()->toISOString()]);

            $cursor = $this->resolveSinceCursor(is_string($sinceParam) ? $sinceParam : null);
            $seenKeys = [];

            // Catch up any notifications created since the client's last cursor
            if ($cursor !== null && $matcher->isUserAlertsEnabled($userId)) {
                $missed = Notification::where('notification_date', '>', $cursor)
                    ->orderBy('notification_date')
                    ->get();

                foreach ($missed as $notification) {
                    if (!$matcher->matches($notification, $userRole, $userId)) {
                        continue;
                    }
                    $read = NotificationRead::where('notification_id', $notification->notification_id)
                        ->where('user_id', $userId)
                        ->first();
                    if ($read?->is_deleted) {
                        continue;
                    }

                    $formatted = $matcher->formatForClient($notification, $read);
                    $key = $formatted['id'] . '|' . ($formatted['notification_date'] ?? '');
                    $seenKeys[$key] = true;
                    $this->emitEvent('notification', $formatted, $formatted['notification_date'] ?? $formatted['id']);
                }
            }

            // Primary path: real-time Redis Pub/Sub push
            $subscribed = $broker->subscribe(
                onMessage: function (array $payload) use ($matcher, $userRole, $userId, &$seenKeys): void {
                    if (!$matcher->isUserAlertsEnabled($userId)) {
                        return;
                    }
                    if (!$matcher->matches($payload, $userRole, $userId)) {
                        return;
                    }

                    $clientPayload = [
                        'id'                => (string) ($payload['id'] ?? ''),
                        'contract_id'       => $payload['contract_id'] ?? null,
                        'notification_type' => $payload['notification_type'] ?? null,
                        'message'           => (string) ($payload['message'] ?? ''),
                        'notification_date' => $payload['notification_date'] ?? now()->toISOString(),
                        'is_read'           => false,
                        'is_archived'       => false,
                        'is_favorite'       => false,
                    ];

                    $key = $clientPayload['id'] . '|' . $clientPayload['notification_date'];
                    if (isset($seenKeys[$key])) {
                        return;
                    }
                    $seenKeys[$key] = true;

                    $this->emitEvent('notification', $clientPayload, $clientPayload['notification_date']);
                },
                onHeartbeat: function (): void {
                    echo ": heartbeat\n\n";
                    $this->flushOutput();
                },
                durationSeconds: $duration,
                heartbeatSeconds: 15
            );

            // Automatic fallback when Redis is unavailable (e.g., local test mode)
            if (!$subscribed && !app()->environment('testing')) {
                $pollCursor = now();
                $startedAt = time();
                $lastHeartbeat = $startedAt;

                while (!connection_aborted() && (time() - $startedAt) < $duration) {
                    sleep(1);

                    if ($matcher->isUserAlertsEnabled($userId)) {
                        $fresh = Notification::where('notification_date', '>', $pollCursor)
                            ->orderBy('notification_date')
                            ->get();

                        foreach ($fresh as $notification) {
                            if ($notification->notification_date && $notification->notification_date->gt($pollCursor)) {
                                $pollCursor = $notification->notification_date;
                            }
                            if (!$matcher->matches($notification, $userRole, $userId)) {
                                continue;
                            }

                            $formatted = $matcher->formatForClient($notification);
                            $key = $formatted['id'] . '|' . ($formatted['notification_date'] ?? '');
                            if (isset($seenKeys[$key])) {
                                continue;
                            }
                            $seenKeys[$key] = true;
                            $this->emitEvent('notification', $formatted, $formatted['notification_date'] ?? $formatted['id']);
                            $lastHeartbeat = time();
                        }
                    }

                    if ((time() - $lastHeartbeat) >= 15) {
                        echo ": heartbeat\n\n";
                        $this->flushOutput();
                        $lastHeartbeat = time();
                    }
                }
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function resolveSinceCursor(?string $since): ?Carbon
    {
        if ($since === null || trim($since) === '') {
            return null;
        }

        try {
            return Carbon::parse($since);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emitEvent(string $event, array $data, ?string $id = null): void
    {
        if ($id !== null && $id !== '') {
            echo "id: {$id}\n";
        }
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        $this->flushOutput();
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }
}
