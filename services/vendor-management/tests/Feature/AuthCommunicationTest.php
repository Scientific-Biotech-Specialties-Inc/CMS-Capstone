<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthCommunicationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test that the service correctly communicates with auth-service and allows access.
     */
    public function test_it_communicates_with_auth_service_and_allows_access_with_valid_token()
    {
        // 1. Mock the Auth Service response
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'test@example.com',
                    'role' => 'Admin',
                    'permissions' => ['cms.partners.view', 'cms.partners.create'],
                    'department' => 'Sales & Marketing'
                ]
            ], 200)
        ]);

        // 2. Call a protected route in vendor-management
        $response = $this->withHeaders([
            'Authorization' => 'Bearer fake-valid-token',
            'Accept' => 'application/json',
        ])->getJson('/api/suppliers');

        // 3. Assertions
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page']);
        
        // Ensure the HTTP call was actually made to the correct internal URL
        Http::assertSent(function ($request) {
            return $request->url() === 'http://auth-service:8000/api/internal/verify-token' &&
                   $request['token'] === 'fake-valid-token';
        });
    }

    /**
     * Test that access is denied when the auth-service returns invalid.
     */
    public function test_it_denies_access_when_auth_service_returns_invalid()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => false,
                'message' => 'Invalid token'
            ], 401)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json',
        ])->getJson('/api/suppliers');

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Unauthenticated or session expired.');
    }

    /**
     * Test that access is denied when the user lacks specific permissions.
     */
    public function test_it_denies_access_when_user_lacks_permission()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'test@example.com',
                    'role' => 'Sales',
                    'permissions' => ['cms.partners.view'], // Lacks 'cms.partners.create'
                    'department' => 'Sales & Marketing'
                ]
            ], 200)
        ]);

        // Try to POST to suppliers (which requires 'cms.partners.create')
        $response = $this->withHeaders([
            'Authorization' => 'Bearer sales-token',
            'Accept' => 'application/json',
        ])->postJson('/api/suppliers');

        $response->assertStatus(403);
    }
}
