<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lightweight, zero-dependency Redis RESP Pub/Sub broker over PHP sockets.
 * Works with the existing php:8.3-cli image without phpredis or predis,
 * and fails open gracefully if Redis is unavailable.
 */
class NotificationEventBroker
{
    public const CHANNEL = 'cms:notifications';

    private string $host;
    private int $port;
    private ?string $password;

    public function __construct(?string $host = null, ?int $port = null, ?string $password = null)
    {
        $this->host = $host ?? (string) config('database.redis.default.host', '127.0.0.1');
        $this->port = $port ?? (int) config('database.redis.default.port', 6379);
        $rawPass = $password ?? config('database.redis.default.password');
        $this->password = is_string($rawPass) && $rawPass !== '' && $rawPass !== 'null' ? $rawPass : null;
    }

    /**
     * Publishes a notification payload to Redis Pub/Sub.
     * Never throws — returns false if Redis cannot be reached.
     *
     * @param array<string, mixed> $payload
     */
    public function publish(array $payload): bool
    {
        $socket = $this->connect(1.0);
        if ($socket === null) {
            return false;
        }

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            $this->writeCommand($socket, ['PUBLISH', self::CHANNEL, $json]);
            $this->readResp($socket);

            return true;
        } catch (Throwable $e) {
            Log::warning('NotificationEventBroker: failed to publish event', [
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            @fclose($socket);
        }
    }

    /**
     * Subscribes to Redis Pub/Sub for up to $durationSeconds, invoking $onMessage
     * immediately on each received event and $onHeartbeat periodically.
     * Returns false if Redis could not be reached so callers can use DB fallback.
     *
     * @param callable(array<string, mixed>): void $onMessage
     * @param callable(): void                     $onHeartbeat
     */
    public function subscribe(
        callable $onMessage,
        callable $onHeartbeat,
        int $durationSeconds = 25,
        int $heartbeatSeconds = 15
    ): bool {
        $socket = $this->connect(1.5);
        if ($socket === null) {
            return false;
        }

        try {
            $this->writeCommand($socket, ['SUBSCRIBE', self::CHANNEL]);
            // Consume initial subscribe confirmation frame
            $this->readResp($socket);

            $startedAt = time();
            $lastHeartbeat = $startedAt;

            while (!connection_aborted() && (time() - $startedAt) < $durationSeconds) {
                $read = [$socket];
                $write = null;
                $except = null;
                $ready = @stream_select($read, $write, $except, 1, 0);

                if ($ready === false) {
                    break;
                }

                if ($ready > 0) {
                    $frame = $this->readResp($socket);
                    if ($frame === null) {
                        break;
                    }

                    if (
                        is_array($frame)
                        && count($frame) >= 3
                        && $frame[0] === 'message'
                        && is_string($frame[2])
                    ) {
                        $decoded = json_decode($frame[2], true);
                        if (is_array($decoded)) {
                            $onMessage($decoded);
                            $lastHeartbeat = time();
                        }
                    }
                }

                $now = time();
                if (($now - $lastHeartbeat) >= $heartbeatSeconds) {
                    $onHeartbeat();
                    $lastHeartbeat = $now;
                }
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('NotificationEventBroker: subscribe loop interrupted', [
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            @fclose($socket);
        }
    }

    /**
     * @return resource|null
     */
    private function connect(float $timeoutSeconds)
    {
        if (app()->environment('testing') && $this->host === '127.0.0.1') {
            return null;
        }

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->host, $this->port),
            $errno,
            $errstr,
            $timeoutSeconds
        );

        if (!is_resource($socket)) {
            return null;
        }

        stream_set_timeout($socket, 2);

        if ($this->password !== null) {
            $this->writeCommand($socket, ['AUTH', $this->password]);
            $authResp = $this->readResp($socket);
            if ($authResp === null) {
                @fclose($socket);
                return null;
            }
        }

        return $socket;
    }

    /**
     * @param resource       $socket
     * @param list<string>   $args
     */
    private function writeCommand($socket, array $args): void
    {
        $buffer = '*' . count($args) . "\r\n";
        foreach ($args as $arg) {
            $buffer .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
        }

        fwrite($socket, $buffer);
    }

    /**
     * Reads a single RESP value from the socket.
     *
     * @param resource $socket
     * @return mixed
     */
    private function readResp($socket): mixed
    {
        $line = fgets($socket);
        if ($line === false) {
            return null;
        }

        $line = rtrim($line, "\r\n");
        if ($line === '') {
            return null;
        }

        $prefix = $line[0];
        $payload = substr($line, 1);

        return match ($prefix) {
            '+' => $payload,
            '-' => null,
            ':' => (int) $payload,
            '$' => $this->readBulkString($socket, (int) $payload),
            '*' => $this->readArray($socket, (int) $payload),
            default => null,
        };
    }

    /**
     * @param resource $socket
     */
    private function readBulkString($socket, int $length): ?string
    {
        if ($length < 0) {
            return null;
        }

        $remaining = $length;
        $data = '';
        while ($remaining > 0) {
            $chunk = fread($socket, $remaining);
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        // Consume trailing \r\n
        fread($socket, 2);

        return $data;
    }

    /**
     * @param resource $socket
     * @return list<mixed>|null
     */
    private function readArray($socket, int $count): ?array
    {
        if ($count < 0) {
            return null;
        }

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->readResp($socket);
        }

        return $items;
    }
}
