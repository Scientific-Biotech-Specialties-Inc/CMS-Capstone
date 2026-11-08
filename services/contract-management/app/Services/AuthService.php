<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.auth.url', env('AUTH_SERVICE_URL', 'http://auth-service:8000/api'));
    }

    /**
     * Verify a token with the auth-service.
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Internal-Service' => 'contract-management', // For auditing
                'X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET'),
            ])->post("{$this->baseUrl}/internal/verify-token", [
                'token' => $token,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Auth verification failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Auth service connection error', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Proxy user creation to the auth-service.
     */
    public function createUser(array $data, string $token, ?string $sessionId = null): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => ($sessionId ?: request()->header('X-Session-ID') ?: request()->cookie('session_id')) ?: '',
                'X-Internal-Service' => 'contract-management-admin',
            ])->post("{$this->baseUrl}/admin/users", $data);

            return $response->successful() ? $response->json() : [
                'error' => true, 
                'status' => $response->status(), 
                'message' => $response->json('message') ?? 'Failed to create user',
                'errors' => $response->json('errors') ?? []
            ];
        } catch (\Exception $e) {
            Log::error('Auth service creation error', ['message' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Connection failed'];
        }
    }
    /**
     * Fetch multiple users by IDs from auth-service.
     */
    public function getUsersBatch(array $ids): array
    {
        if (empty($ids)) return [];

        try {
            $idsString = implode(',', $ids);
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Internal-Service' => 'contract-management',
                'X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET'),
            ])->get("{$this->baseUrl}/internal/users-batch", [
                'ids' => $idsString,
            ]);

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            Log::warning('Auth service getUsersBatch failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Auth service connection error in getUsersBatch', [
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
}

