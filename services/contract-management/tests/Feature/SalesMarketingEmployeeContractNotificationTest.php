<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\ContractStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesMarketingEmployeeContractNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\SyncContractToMeilisearch::class,
            \App\Jobs\RemoveContractFromMeilisearch::class,
        ]);
    }

    protected function mockAuth(string $role, string $department): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 42,
                    'email' => 'test-user@sbsi.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'role' => $role,
                    'permissions' => ['cms.contracts.create'],
                    'department' => $department,
                ]
            ], 200)
        ]);
    }

    public function test_it_sends_manager_notification_when_sales_marketing_employee_creates_contract()
    {
        \Illuminate\Support\Facades\Cache::store('file')->flush();
        
        // Mock Auth and Notification Service together
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 42,
                    'email' => 'test-user@sbsi.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'role' => 'Employee',
                    'permissions' => ['cms.contracts.create'],
                    'department' => 'Sales & Marketing',
                ]
            ], 200),
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201)
        ]);

        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/contracts', [
            'businessPartner' => 'Globe Telecom',
            'category' => 'Service Agreement',
            'status' => 'Notarized PDF',
            'itemCode' => 'ITM-99',
            'description' => 'Test Sales & Marketing Contract',
            'serialNo' => 'SN-999',
            'sbuNumber' => 'SBU-99',
            'region' => 'Luzon',
            'startDate' => '2026-06-01',
            'endDate' => '2026-07-01',
        ]);

        $response->assertStatus(201);

        Http::assertSent(function ($request) {
            if ($request->url() === 'http://notification:8000/api/internal/push') {
                $payload = json_decode($request->body(), true);
                return $payload['notification_type'] === 'sales_marketing_review'
                    && $payload['target_roles'] === 'Manager'
                    && str_contains($payload['message'], 'John Doe sent a contract and is requesting to review it.');
            }
            return true;
        });
    }

    public function test_it_does_not_send_notification_when_sales_employee_creates_contract()
    {
        \Illuminate\Support\Facades\Cache::store('file')->flush();
        
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 42,
                    'email' => 'test-user@sbsi.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'role' => 'Sales',
                    'permissions' => ['cms.contracts.create'],
                    'department' => 'Sales',
                ]
            ], 200)
        ]);

        $category = ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/contracts', [
            'businessPartner' => 'Globe Telecom',
            'category' => 'Service Agreement',
            'status' => 'Notarized PDF',
            'itemCode' => 'ITM-99',
            'description' => 'Test Sales Contract',
            'serialNo' => 'SN-888',
            'sbuNumber' => 'SBU-99',
            'region' => 'Luzon',
            'startDate' => '2026-06-01',
            'endDate' => '2026-07-01',
        ]);

        $response->assertStatus(201);

        Http::assertNotSent(function ($request) {
            return $request->url() === 'http://notification:8000/api/internal/push';
        });
    }
}
