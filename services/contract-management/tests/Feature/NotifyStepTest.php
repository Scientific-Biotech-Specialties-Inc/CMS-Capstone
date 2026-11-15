<?php

namespace Tests\Feature;

use App\Models\ApprovalTask;
use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 7 of the dynamic approval workflow engine plan: notification
 * generalization (decision #13). POST /notify-step notifies the role(s)
 * currently responsible; /notify-manager stays as an alias. Helpers are
 * intentionally duplicated from ApprovalEngineTest (per the Phase 7 plan:
 * small, avoids cross-file test coupling).
 */
class NotifyStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([\App\Jobs\SyncContractToMeilisearch::class, \App\Jobs\RemoveContractFromMeilisearch::class]);
        DB::table('contract_regions')->insertOrIgnore(['region_name' => 'Luzon']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Approved']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Rejected']);
    }

    private array $httpFakes = [];
    private int $authenticatedUserId = 42;

    private function fakeAuth(array $overrides = []): void
    {
        $this->authenticatedUserId = $overrides['id'] ?? 42;
        $this->httpFakes['http://auth-service:8000/api/internal/verify-token'] = Http::response([
            'valid' => true,
            'user' => array_merge([
                'id' => 42,
                'email' => 'sales@sbsi.com',
                'first_name' => 'Sales',
                'last_name' => 'User',
                'role' => 'Sales',
                'permissions' => ['cms.contracts.view', 'cms.contracts.create', 'cms.contracts.edit', 'cms.contracts.approve'],
                'department' => 'Sales & Marketing',
            ], $overrides),
        ], 200);
        $this->httpFakes['http://notification:8000/api/internal/push'] = Http::response(['success' => true], 201);

        Http::fake($this->httpFakes);
    }

    private function fakeActiveHolders(array $roleIdToUserIds): void
    {
        foreach ($roleIdToUserIds as $roleId => $userIds) {
            $data = array_map(fn ($uid) => [
                'id' => $uid, 'email' => "user{$uid}@sbsi.com",
                'first_name' => 'User', 'last_name' => (string) $uid, 'department' => 'Operations',
            ], $userIds);

            $this->httpFakes["http://auth-service:8000/api/internal/roles/{$roleId}/active-holders"] = Http::response([
                'data' => $data, 'empty' => empty($data),
            ], 200);
        }

        Http::fake($this->httpFakes);
    }

    private function fakeRoleDescribe(array $roleIdToDescribe): void
    {
        foreach ($roleIdToDescribe as $roleId => $describe) {
            $this->httpFakes["http://auth-service:8000/api/internal/roles/{$roleId}/describe"] = Http::response($describe, 200);
        }

        Http::fake($this->httpFakes);
    }

    private function makeCategory(string $name = 'Service Agreement'): ContractCategory
    {
        return ContractCategory::create(['category_name' => $name, 'is_active' => true]);
    }

    private function makeActiveWorkflow(ContractCategory $category, array $steps): Workflow
    {
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Test chain',
            'status' => 'active',
            'resubmit_mode' => 'restart',
        ]);

        foreach ($steps as $i => $stepDef) {
            $step = WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'order_index' => $i + 1,
                'mode' => $stepDef['mode'] ?? 'sequential',
            ]);
            foreach ($stepDef['roles'] as $roleId => $roleName) {
                WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => $roleId, 'role_name_snapshot' => $roleName]);
            }
        }

        return $workflow->load('steps.workflowStepRoles');
    }

    private function createContractPayload(array $overrides = []): array
    {
        return array_merge([
            'document_ids' => [$this->createDraftPdf($this->authenticatedUserId)],
            'bp_name' => 'Test Vendor',
            'category' => 'Service Agreement',
            'item_code' => 'ITM-ENG-' . uniqid(),
            'description' => 'Engine test contract',
            'serial_number' => 'SN-ENG-' . uniqid(),
            'sbu_number' => 'SBU-1',
            'region' => 'Luzon',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $overrides);
    }

    private function createEngineContract(): string
    {
        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());

        return $createResp->json('data.contract_db_id');
    }

    private function pushedNotifications(): array
    {
        $payloads = [];
        foreach (Http::recorded() as [$request, $response]) {
            if ($request->url() === 'http://notification:8000/api/internal/push') {
                $payloads[] = json_decode($request->body(), true);
            }
        }
        return $payloads;
    }

    // ── Legacy path (unchanged behavior, new canonical endpoint) ──────

    public function test_legacy_notify_increments_count_and_targets_manager_admin()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth();
        $this->makeCategory(); // no active workflow → legacy path

        $contractId = $this->createEngineContract();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step");

        $response->assertOk();
        $response->assertJsonPath('targetRoles', 'Manager,Admin');
        $response->assertJsonPath('count', 1);

        $pushes = $this->pushedNotifications();
        $this->assertCount(1, $pushes);
        $this->assertEquals('manager_approval_request', $pushes[0]['notification_type']);
        $this->assertEquals('Manager,Admin', $pushes[0]['target_roles']);
    }

    public function test_legacy_notify_blocks_at_limit()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth();
        $this->makeCategory();

        $contractId = $this->createEngineContract();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step")->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step")->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step");

        $response->assertStatus(429);
    }

    public function test_old_notify_manager_route_still_works_as_alias()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth();
        $this->makeCategory();

        $contractId = $this->createEngineContract();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-manager");

        $response->assertOk();
        $response->assertJsonPath('message', 'Manager notified successfully.');
        $response->assertJsonPath('targetRoles', 'Manager,Admin');
    }

    // ── Engine path ───────────────────────────────────────────────────

    public function test_engine_routed_notify_targets_current_step_roles()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
            ['roles' => [351 => 'Finance']],
        ]);
        $this->fakeActiveHolders([350 => [10], 351 => [20]]);
        $this->fakeRoleDescribe([
            350 => ['exists' => true, 'deleted' => false, 'current_name' => 'Regulatory', 'was_renamed' => false, 'name_history' => []],
            // Snapshot says 'Finance' but the role was renamed since —
            // the nudge must target the LIVE name to reach anyone.
            351 => ['exists' => true, 'deleted' => false, 'current_name' => 'Finance & Accounts', 'was_renamed' => true, 'name_history' => [['old_name' => 'Finance']]],
        ]);

        $contractId = $this->createEngineContract();

        // Advance to step 2.
        $task = ApprovalTask::where('auth_role_id', 350)->first();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", ['decision' => 'approved'])
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step");

        $response->assertOk();
        $response->assertJsonPath('targetRoles', 'Finance & Accounts');

        $pushes = $this->pushedNotifications();
        $last = end($pushes);
        $this->assertEquals('approval_step_nudge', $last['notification_type']);
        $this->assertEquals('Finance & Accounts', $last['target_roles']);
        $this->assertStringContainsString('Finance & Accounts', $last['message']);
    }

    public function test_engine_routed_parallel_step_targets_all_roles()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['mode' => 'parallel', 'roles' => [350 => 'Sales', 351 => 'Accounting']],
        ]);
        $this->fakeActiveHolders([350 => [10], 351 => [20]]);
        $this->fakeRoleDescribe([
            350 => ['exists' => true, 'deleted' => false, 'current_name' => 'Sales', 'was_renamed' => false, 'name_history' => []],
            351 => ['exists' => true, 'deleted' => false, 'current_name' => 'Accounting', 'was_renamed' => false, 'name_history' => []],
        ]);

        $contractId = $this->createEngineContract();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step");

        $response->assertOk();
        $response->assertJsonPath('targetRoles', 'Sales,Accounting');

        $pushes = $this->pushedNotifications();
        $this->assertEquals('Sales,Accounting', end($pushes)['target_roles']);
    }

    public function test_engine_notify_count_resets_on_step_advance()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
            ['roles' => [351 => 'Finance']],
        ]);
        $this->fakeActiveHolders([350 => [10], 351 => [20]]);
        $this->fakeRoleDescribe([
            350 => ['exists' => true, 'deleted' => false, 'current_name' => 'Regulatory', 'was_renamed' => false, 'name_history' => []],
            351 => ['exists' => true, 'deleted' => false, 'current_name' => 'Finance', 'was_renamed' => false, 'name_history' => []],
        ]);

        $contractId = $this->createEngineContract();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step")->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step")->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step")->assertStatus(429);

        // Approving step 1 advances to step 2 → counter resets.
        $task = ApprovalTask::where('auth_role_id', 350)->first();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", ['decision' => 'approved'])
            ->assertOk();

        $this->assertDatabaseHas('contracts', ['contract_id' => $contractId, 'notify_manager_count' => 0]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step");

        $response->assertOk();
        $response->assertJsonPath('count', 1);
        $response->assertJsonPath('targetRoles', 'Finance');
    }

    public function test_engine_notify_blocked_at_limit()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
        ]);
        $this->fakeActiveHolders([350 => [10]]);
        $this->fakeRoleDescribe([
            350 => ['exists' => true, 'deleted' => false, 'current_name' => 'Regulatory', 'was_renamed' => false, 'name_history' => []],
        ]);

        $contractId = $this->createEngineContract();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step")->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step")->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/notify-step");

        $response->assertStatus(429);
        $this->assertDatabaseHas('contracts', ['contract_id' => $contractId, 'notify_manager_count' => 2]);
    }

    public function test_progress_response_includes_current_step_roles()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
        ]);
        $this->fakeActiveHolders([350 => [10]]);
        $this->fakeRoleDescribe([
            350 => ['exists' => true, 'deleted' => false, 'current_name' => 'Regulatory', 'was_renamed' => false, 'name_history' => []],
        ]);

        $contractId = $this->createEngineContract();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson("/api/contracts/{$contractId}/approval-progress");

        $response->assertOk();
        $response->assertJsonPath('data.current_step_roles', ['Regulatory']);
    }
}
