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

    public function verifyToken(string $token): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Internal-Service' => 'search',
            ])->post("{$this->baseUrl}/internal/verify-token", [
                'token' => $token,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Auth service connection error in search', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
