<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies user bearer tokens against auth-service, matching the pattern
 * already used by the other services' AuthService + AuthenticateInternal
 * middleware.
 */
class AuthService
{
    protected string $baseUrl;
    protected string $secret;

    public function __construct()
    {
        $this->baseUrl = env('AUTH_SERVICE_URL', 'http://auth-service:8000/api');
        $this->secret  = env('INTERNAL_SERVICE_SECRET', '');
    }

    public function verifyToken(string $token): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept'             => 'application/json',
                'X-Internal-Service' => 'analytics-service',
                'X-Internal-Secret'  => $this->secret,
            ])->post("{$this->baseUrl}/internal/verify-token", [
                'token' => $token,
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Auth service connection error in analytics-service', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
