<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_communicates_with_auth_service_and_enforces_contract_permissions()
    {
        \Illuminate\Support\Facades\Cache::store('file')->flush();
        // Mock the Auth Service
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'manager@example.com',
                    'role' => 'Manager',
                    'permissions' => ['cms.contracts.view'],
                    'department' => 'Sales & Marketing'
                ]
            ], 200)
        ]);

        // Access protected contracts route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer manager-token',
            'Accept' => 'application/json',
        ])->getJson('/api/contracts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'contract_id',
                    'bp_name',
                    'category',
                    'approval_status',
                    'workflow_status',
                    'item_code',
                    'description',
                    'serial_number',
                    'sbu_number',
                    'region',
                    'start_date',
                    'end_date',
                    'created_by',
                    'documents',
                ]
            ]
        ]);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'http://auth-service:8000/api/internal/verify-token';
        });
    }

    public function test_it_denies_contract_access_without_permission()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 2,
                    'email' => 'sales@example.com',
                    'role' => 'Sales',
                    'permissions' => [], // No permissions
                    'department' => 'Sales & Marketing'
                ]
            ], 200)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer sales-token',
            'Accept' => 'application/json',
        ])->getJson('/api/contracts');

        $response->assertStatus(403);
    }
}
