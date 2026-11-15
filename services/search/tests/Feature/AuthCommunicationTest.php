<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthCommunicationTest extends TestCase
{
    public function test_it_communicates_with_auth_service_for_search()
    {
        $mockMeili = $this->createMock(\App\Services\MeilisearchService::class);
        $mockMeili->method('search')->willReturn([
            'hits' => [],
            'estimatedTotalHits' => 0,
        ]);
        $this->app->instance(\App\Services\MeilisearchService::class, $mockMeili);

        $baseUrl = config('services.auth.url', env('AUTH_SERVICE_URL', 'http://auth-service:8000/api'));

        Http::fake([
            "{$baseUrl}/internal/verify-token" => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'user@example.com',
                    'role' => 'Sales',
                    'permissions' => ['cms.contracts.view']
                ]
            ], 200)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer user-token',
            'Accept' => 'application/json',
        ])->getJson('/api/search?q=test');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'query', 'total']);
    }

    public function test_it_blocks_search_without_token()
    {
        $response = $this->getJson('/api/search');

        $response->assertStatus(401);
    }
}
