<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractApproval;
use App\Models\ContractCategory;
use App\Models\ContractApprovalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * US-023's mandatory approval gate is DISABLED BY DEFAULT
 * (config('services.features.high_risk_approval_gate_enabled') /
 * HIGH_RISK_APPROVAL_GATE_ENABLED). AI Risk Assessment is currently advisory
 * only: High/Critical risk surfaces a flag/warning on the contract but never
 * blocks approval. The gate's supporting code is kept in place, inert, and
 * covered here by re-enabling the flag per-test so it's ready to switch back
 * on later without further code changes.
 */
class HighRiskApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::store('file')->flush();
        $this->seedContractLookups();

        // Prevent the (unrelated) Meilisearch sync job from making a real
        // network call during these tests — matches how a real environment
        // would have a live meilisearch container, which isn't available here.
        Bus::fake([\App\Jobs\SyncContractToMeilisearch::class]);
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

    private function fakeManagerAuth(): void
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
                    'permissions' => ['cms.contracts.approve', 'cms.contracts.view'],
                    'department' => 'Management',
                ],
            ], 200),
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201),
        ]);
    }

    private function makeContract(array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'category_id'         => ContractCategory::first()->category_id,
            'approval_status_id'  => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
            'bp_name'             => 'Test Vendor',
            'item_code'           => 'ITM-RISK',
            'description'         => 'Desc',
            'serial_number'       => 'SN-RISK-1',
            'sbu_number'          => 'SBU-123',
            'region_id'           => \Illuminate\Support\Facades\DB::table('contract_regions')->first()->region_id,
            'start_date'          => '2026-01-01',
            'end_date'            => '2026-12-31',
            'created_by'          => 42,
            'notify_manager_count' => 0,
        ], $overrides));
    }

    // ── Default behavior: gate disabled, AI Risk Assessment is advisory only ──

    public function test_high_risk_contract_can_still_be_approved_by_default_gate_disabled()
    {
        $this->fakeManagerAuth();
        Http::fake([
            'http://ai-service:8000/api/internal/contracts/*/risk-level' => Http::response([
                'risk_level' => 'critical',
            ], 200),
        ] + $this->currentFakes());

        $contract = $this->makeContract();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status' => 'Approved',
        ]);

        // Approval succeeds despite Critical risk — advisory only, not a gate.
        $response->assertStatus(200);
        $this->assertDatabaseHas('contracts', [
            'contract_id'        => $contract->contract_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Approved')->first()->approval_status_id,
        ]);

        // No gate/SLA record is created while the flag is off.
        $this->assertDatabaseCount('contract_approvals', 0);

        // ai-service is never even consulted for the gate decision since the
        // config check short-circuits before the risk-level lookup.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'risk-level'));
    }

    public function test_high_risk_approval_gate_endpoint_reports_disabled_state()
    {
        $this->fakeManagerAuth();
        Http::fake([
            'http://ai-service:8000/api/internal/contracts/*/risk-level' => Http::response([
                'risk_level' => 'high',
            ], 200),
        ] + $this->currentFakes());

        $contract = $this->makeContract(['serial_number' => 'SN-RISK-2']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->getJson("/api/contracts/{$contract->contract_id}/high-risk-approval");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'risk_level'                  => 'high',
                'requires_high_risk_approval' => false,
                'blocked'                     => false,
                'approval'                    => null,
            ],
        ]);
        $this->assertDatabaseCount('contract_approvals', 0);
    }

    public function test_recording_a_high_risk_decision_is_rejected_while_gate_disabled()
    {
        $this->fakeManagerAuth();

        $contract = $this->makeContract(['serial_number' => 'SN-RISK-3']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->postJson("/api/contracts/{$contract->contract_id}/high-risk-approval", [
            'decision'  => 'approved',
            'rationale' => 'Reviewed with legal.',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('contract_approvals', 0);
    }

    // ── Gate re-enabled (legacy behavior, kept working for future use) ──

    public function test_approving_a_high_risk_contract_is_blocked_when_gate_re_enabled()
    {
        config(['services.features.high_risk_approval_gate_enabled' => true]);

        $this->fakeManagerAuth();
        Http::fake([
            'http://ai-service:8000/api/internal/contracts/*/risk-level' => Http::response([
                'risk_level' => 'high',
            ], 200),
        ] + $this->currentFakes());

        $contract = $this->makeContract(['serial_number' => 'SN-RISK-4']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status' => 'Approved',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'requires_high_risk_approval' => true,
            'risk_level'                  => 'high',
        ]);

        $this->assertDatabaseHas('contracts', [
            'contract_id'        => $contract->contract_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
        ]);

        $this->assertDatabaseHas('contract_approvals', [
            'contract_id' => $contract->contract_id,
            'risk_level'  => 'high',
            'decision'    => null,
        ]);
    }

    public function test_recording_high_risk_approval_unblocks_the_gate_when_re_enabled()
    {
        config(['services.features.high_risk_approval_gate_enabled' => true]);

        $this->fakeManagerAuth();
        Http::fake([
            'http://ai-service:8000/api/internal/contracts/*/risk-level' => Http::response([
                'risk_level' => 'critical',
            ], 200),
        ] + $this->currentFakes());

        $contract = $this->makeContract(['serial_number' => 'SN-RISK-5']);

        $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status' => 'Approved',
        ])->assertStatus(422);

        $recordResponse = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->postJson("/api/contracts/{$contract->contract_id}/high-risk-approval", [
            'decision'  => 'approved',
            'rationale' => 'Reviewed with legal, risk is acceptable given mitigations in place.',
        ]);

        $recordResponse->assertStatus(200);
        $this->assertDatabaseHas('contract_approvals', [
            'contract_id' => $contract->contract_id,
            'decision'    => 'approved',
            'approver_id' => 99,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status' => 'Approved',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('contracts', [
            'contract_id'        => $contract->contract_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Approved')->first()->approval_status_id,
        ]);
    }

    public function test_low_risk_contract_can_be_approved_without_a_gate_record_when_re_enabled()
    {
        config(['services.features.high_risk_approval_gate_enabled' => true]);

        $this->fakeManagerAuth();
        Http::fake([
            'http://ai-service:8000/api/internal/contracts/*/risk-level' => Http::response([
                'risk_level' => 'low',
            ], 200),
        ] + $this->currentFakes());

        $contract = $this->makeContract(['serial_number' => 'SN-RISK-6']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status' => 'Approved',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('contract_approvals', 0);
    }

    public function test_high_risk_approval_requires_rationale_when_re_enabled()
    {
        config(['services.features.high_risk_approval_gate_enabled' => true]);

        $this->fakeManagerAuth();
        Http::fake([
            'http://ai-service:8000/api/internal/contracts/*/risk-level' => Http::response([
                'risk_level' => 'high',
            ], 200),
        ] + $this->currentFakes());

        $contract = $this->makeContract(['serial_number' => 'SN-RISK-7']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->postJson("/api/contracts/{$contract->contract_id}/high-risk-approval", [
            'decision'  => 'approved',
            'rationale' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_rejecting_a_contract_is_never_gated_by_risk_level()
    {
        // Even with the gate re-enabled, rejection is never gated — the gate
        // only ever applies to the 'Approved' transition.
        config(['services.features.high_risk_approval_gate_enabled' => true]);

        $this->fakeManagerAuth();
        Http::fake($this->currentFakes());

        $contract = $this->makeContract(['serial_number' => 'SN-RISK-8']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status'  => 'Rejected',
            'rejection_reason' => 'Not a fit.',
        ]);

        $response->assertStatus(200);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'risk-level'));
    }

    public function test_creator_cannot_approve_own_contract_when_enforce_sod_is_enabled()
    {
        config(['services.features.enforce_sod' => true]);

        $this->fakeManagerAuth(); // User ID 99
        Http::fake($this->currentFakes());

        // Contract created by the same user (ID 99)
        $contract = $this->makeContract(['created_by' => 99, 'serial_number' => 'SN-SOD-1']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status' => 'Approved',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You cannot approve or reject a contract you created.',
        ]);
    }

    public function test_creator_can_approve_when_enforce_sod_is_disabled()
    {
        config(['services.features.enforce_sod' => false]);

        $this->fakeManagerAuth(); // User ID 99
        Http::fake($this->currentFakes());

        $contract = $this->makeContract(['created_by' => 99, 'serial_number' => 'SN-SOD-2']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->patchJson("/api/contracts/{$contract->contract_id}/status", [
            'approval_status' => 'Approved',
        ]);

        $response->assertStatus(200);
    }

    public function test_creator_cannot_record_high_risk_approval_on_own_contract_when_sod_enabled()
    {
        config([
            'services.features.high_risk_approval_gate_enabled' => true,
            'services.features.enforce_sod' => true,
        ]);

        $this->fakeManagerAuth(); // User ID 99
        Http::fake($this->currentFakes());

        $contract = $this->makeContract(['created_by' => 99, 'serial_number' => 'SN-SOD-3']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Accept'        => 'application/json',
        ])->postJson("/api/contracts/{$contract->contract_id}/high-risk-approval", [
            'decision'  => 'approved',
            'rationale' => 'Valid business case justified by leadership.',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You cannot record a high-risk approval decision on a contract you created.',
        ]);
    }

    /**
     * Re-declares the auth/notification fakes so they can be merged with a
     * per-test ai-service fake without losing the base fakes (Http::fake
     * merges arrays across calls within the same test, but being explicit
     * keeps each test self-contained and easy to read).
     */
    private function currentFakes(): array
    {
        return [
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 99,
                    'email' => 'manager@sbsi.com',
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'role' => 'Manager',
                    'permissions' => ['cms.contracts.approve', 'cms.contracts.view'],
                    'department' => 'Management',
                ],
            ], 200),
            'http://notification:8000/api/internal/push' => Http::response(['success' => true], 201),
        ];
    }
}
