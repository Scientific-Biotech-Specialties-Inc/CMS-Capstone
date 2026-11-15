<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AuthService
{
    protected string $baseUrl;
    protected string $secret;

    public function __construct()
    {
        // Always resolve the auth service on the shared Docker network. The
        // previous hardcoded fallback (http://139.59.125.45/api) sent bearer
        // tokens and internal secrets to an external host whenever
        // AUTH_SERVICE_URL was unset, and broke all local/dev authentication.
        $this->baseUrl = config('services.auth.url', env('AUTH_SERVICE_URL', 'http://auth-service:8000/api'));
        $this->secret  = (string) (env('INTERNAL_SERVICE_SECRET') ?: config('app.internal_service_secret', ''));
    }

    /**
     * Verify a token with the auth-service.
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $secret = env('INTERNAL_SERVICE_SECRET') ?: $this->secret;
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Internal-Service' => 'notification',
                'X-Internal-Secret' => $secret,
            ])->post("{$this->baseUrl}/internal/verify-token", [
                'token' => $token,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Auth verification failed in notification', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Auth service connection error in notification', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get user email and profile details by user ID internally.
     */
    public function getUserInfo(int $userId): ?array
    {
        try {
            $secret = env('INTERNAL_SERVICE_SECRET') ?: $this->secret;
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $secret,
            ])->get("{$this->baseUrl}/internal/users/{$userId}");

            if ($response->successful()) {
                $json = $response->json();
                return $json['data'] ?? $json;
            }

            Log::warning("Auth service internal user lookup failed", [
                'user_id' => $userId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Auth service user lookup connection error', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all active users belonging to any of the specified roles.
     *
     * @param array<int, string> $roles
     * @return array<int, array{id: int, email: string, first_name: string, last_name: string}>
     */
    public function getUsersByRoles(array $roles): array
    {
        try {
            $secret = env('INTERNAL_SERVICE_SECRET') ?: $this->secret;
            $response = Http::withHeaders([
                'Accept'            => 'application/json',
                'X-Internal-Secret' => $secret,
            ])->get("{$this->baseUrl}/internal/users-by-roles", [
                'roles' => implode(',', $roles),
            ]);

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            Log::warning("Auth service internal users-by-roles lookup failed", [
                'roles'  => $roles,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('Auth service users-by-roles connection error', [
                'roles'   => $roles,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
