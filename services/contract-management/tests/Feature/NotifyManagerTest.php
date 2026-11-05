<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\ContractApprovalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotifyManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\SyncContractToMeilisearch::class,
            \App\Jobs\RemoveContractFromMeilisearch::class,
        ]);
        \Illuminate\Support\Facades\Cache::store('file')->flush();
        $this->seedContractLookups();
    }

    private function seedContractLookups(): void
    {
        ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Approved']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Rejected']);
        \Illuminate\Support\Facades\DB::table('contract_regions')->insertOrIgnore([
            ['region_name' => 'Luzon']
        ]);
    }

    public function test_it_increments_count_and_sends_notification()
    {
        // Mock Auth and Notification Service together
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 42,
                    'email' => 'test-user@sbsi.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'role' => 'Sales',
                    'permissions' => ['cms.contracts.view', 'cms.contracts.create'],
                    'department' => 'Sales & Marketing',
                ]
            ], 200),
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201)
        ]);

        $contract = Contract::create([
            'category_id' => ContractCategory::first()->category_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
            'bp_name' => 'Test Vendor',
            'item_code' => 'ITM-TEST',
            'description' => 'Desc',
            'serial_number' => 'SN-123',
            'sbu_number' => 'SBU-123',
            'region_id' => \Illuminate\Support\Facades\DB::table('contract_regions')->first()->region_id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_by' => 42,
            'notify_manager_count' => 0
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson("/api/contracts/{$contract->contract_id}/notify-manager");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('count'));
        $this->assertEquals('Manager notified successfully.', $response->json('message'));

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $contract->contract_id,
            'notify_manager_count' => 1
        ]);

        Http::assertSent(function ($request) use ($contract) {
            if ($request->url() === 'http://notification:8000/api/internal/push') {
                $payload = json_decode($request->body(), true);
                return $payload['notification_type'] === 'manager_approval_request'
                    && $payload['target_roles'] === 'Manager,Admin'
                    && str_contains($payload['message'], "Employee John Doe asked for approval on Contract {$contract->bp_name} ({$contract->item_code})");
            }
            return true;
        });
    }

    public function test_it_blocks_notification_when_limit_reached()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 42,
                    'email' => 'test-user@sbsi.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'role' => 'Sales',
                    'permissions' => ['cms.contracts.view', 'cms.contracts.create'],
                    'department' => 'Sales & Marketing',
                ]
            ], 200),
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201)
        ]);

        $contract = Contract::create([
            'category_id' => ContractCategory::first()->category_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
            'bp_name' => 'Test Vendor',
            'item_code' => 'ITM-TEST-2',
            'description' => 'Desc',
            'serial_number' => 'SN-123-2',
            'sbu_number' => 'SBU-123',
            'region_id' => \Illuminate\Support\Facades\DB::table('contract_regions')->first()->region_id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_by' => 42,
            'notify_manager_count' => 2
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson("/api/contracts/{$contract->contract_id}/notify-manager");

        $response->assertStatus(429);
        $this->assertEquals('The manager is already notified, please wait.', $response->json('message'));

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $contract->contract_id,
            'notify_manager_count' => 2
        ]);

        Http::assertNotSent(function ($request) {
            return $request->url() === 'http://notification:8000/api/internal/push';
        });
    }

    public function test_it_resets_notify_manager_count_on_rejection()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 99,
                    'email' => 'manager@sbsi.com',
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'role' => 'Manager',
                    'permissions' => ['cms.contracts.approve'],
                    'department' => 'Management',
                ]
            ], 200),
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201)
        ]);

        $contract = Contract::create([
            'category_id' => ContractCategory::first()->category_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
            'bp_name' => 'Test Vendor',
            'item_code' => 'ITM-TEST-3',
            'description' => 'Desc',
            'serial_number' => 'SN-123-3',
            'sbu_number' => 'SBU-123',
            'region_id' => \Illuminate\Support\Facades\DB::table('contract_regions')->first()->region_id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_by' => 42,
            'notify_manager_count' => 2
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status' => 'Rejected'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('contracts', [
            'contract_id' => $contract->contract_id,
            'notify_manager_count' => 0,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Rejected')->first()->approval_status_id,
        ]);
    }

    public function test_sales_cannot_notify_manager_on_unowned_contract()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 999, // Different user
                    'email' => 'other-sales@sbsi.com',
                    'first_name' => 'Other',
                    'last_name' => 'Sales',
                    'role' => 'Sales',
                    'permissions' => ['cms.contracts.view', 'cms.contracts.create'],
                    'department' => 'Sales & Marketing',
                ]
            ], 200),
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201)
        ]);

        $contract = Contract::create([
            'category_id' => ContractCategory::first()->category_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
            'bp_name' => 'Test Vendor',
            'item_code' => 'ITM-TEST-UNOWNED',
            'description' => 'Desc',
            'serial_number' => 'SN-123-UNOWNED',
            'sbu_number' => 'SBU-123',
            'region_id' => \Illuminate\Support\Facades\DB::table('contract_regions')->first()->region_id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_by' => 42, // Created by 42, requester is 999
            'notify_manager_count' => 0
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept' => 'application/json'
        ])->postJson("/api/contracts/{$contract->contract_id}/notify-manager");

        $response->assertStatus(403);
        $this->assertEquals('Forbidden.', $response->json('message'));
        $this->assertEquals(0, $contract->fresh()->notify_manager_count);

        Http::assertNotSent(function ($request) {
            return $request->url() === 'http://notification:8000/api/internal/push';
        });
    }
}
