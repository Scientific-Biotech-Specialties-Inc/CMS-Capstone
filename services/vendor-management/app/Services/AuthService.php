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
                'X-Internal-Service' => 'vendor-management', // For auditing
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
    public function createUser(array $data, string $token): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => (request()->header('X-Session-ID') ?: request()->cookie('session_id')) ?: '',
                'X-Internal-Service' => 'vendor-management-admin',
            ])->post("{$this->baseUrl}/admin/users", $data);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'error' => true,
                'status' => $response->status(),
                'message' => $response->json('message') ?? 'Failed to create user in auth-service',
                'errors' => $response->json('errors') ?? []
            ];
        } catch (\Exception $e) {
            Log::error('Auth service creation error', ['message' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Connection to auth-service failed'];
        }
    }
}
