<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalTask;
use App\Models\Contract;
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
 * Phase 5 of the dynamic approval workflow engine plan: runtime routing.
 * Entirely additive and gated by config('services.features.workflow_engine_enabled')
 * (WORKFLOW_ENGINE_ENABLED) — tests explicitly re-enable the flag per test,
 * mirroring the existing HighRiskApprovalGateTest convention, since the
 * flag defaults OFF in .env.
 */
class ApprovalEngineTest extends TestCase
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

    /**
     * Fakes auth-module's active-holders endpoint for one or more roles.
     * $roleIdToUserIds: [roleId => [userId, ...]] — empty array = no holders.
     * Http::fake() replaces the whole fake map on each call, so all fakes
     * registered so far (auth, notification, previously-faked roles) are
     * re-applied together with the new ones here.
     */
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

    private function makeCategory(string $name = 'Service Agreement'): ContractCategory
    {
        return ContractCategory::create(['category_name' => $name, 'is_active' => true]);
    }

    /**
     * Fakes auth-module's role-describe endpoint for one or more roles.
     * $roleIdToDescribe: [roleId => describe-array] in the Phase 1 shape
     * (exists/deleted/current_name/was_renamed/name_history).
     */
    private function fakeRoleDescribe(array $roleIdToDescribe): void
    {
        foreach ($roleIdToDescribe as $roleId => $describe) {
            $this->httpFakes["http://auth-service:8000/api/internal/roles/{$roleId}/describe"] = Http::response($describe, 200);
        }

        Http::fake($this->httpFakes);
    }

    /**
     * Fakes auth-module's users-batch endpoint. $usersById: [userId => 'First Last'].
     * Returns the full set regardless of the requested ids query — enough
     * for progress-enrichment assertions.
     */
    private function fakeUsersBatch(array $usersById): void
    {
        $data = [];
        foreach ($usersById as $uid => $name) {
            $parts = array_pad(explode(' ', (string) $name, 2), 2, '');
            $data[] = ['id' => $uid, 'email' => "user{$uid}@sbsi.com", 'first_name' => $parts[0], 'last_name' => $parts[1]];
        }

        // Wildcard: the client sends ?ids=.. on the query string, and
        // Http::fake matches stub patterns against the full URL.
        $this->httpFakes['http://auth-service:8000/api/internal/users-batch*'] = Http::response(['data' => $data], 200);

        Http::fake($this->httpFakes);
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

    // ── Regression guard: flag off / no active workflow ────────────────

    public function test_contract_creation_unaffected_when_engine_flag_disabled()
    {
        config(['services.features.workflow_engine_enabled' => false]);
        $this->fakeAuth(['role' => 'Manager']);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [['roles' => [350 => 'Manager']]]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());

        $response->assertStatus(201);
        $this->assertDatabaseCount('approval_instances', 0);
        $this->assertEquals('Pending', $response->json('data.approval_status'));
    }

    public function test_contract_creation_unaffected_when_category_has_no_active_workflow()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Manager']);
        $this->makeCategory(); // no workflow attached

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());

        $response->assertStatus(201);
        $this->assertDatabaseCount('approval_instances', 0);
        $this->assertEquals('Pending', $response->json('data.approval_status'));
    }

    // ── Instance creation / sequential advance ─────────────────────────

    public function test_creating_a_contract_with_an_active_workflow_starts_an_instance_pending_not_approved()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Manager']); // even a manager-created contract must NOT auto-approve
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
            ['roles' => [351 => 'Sales']],
        ]);
        $this->fakeActiveHolders([350 => [42], 351 => [43]]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());

        $response->assertStatus(201);
        $this->assertEquals('Pending', $response->json('data.approval_status'));
        $this->assertDatabaseCount('approval_instances', 1);
        $this->assertDatabaseHas('approval_instances', ['run_number' => 1, 'status' => 'in_progress']);
        // Only step 1's task exists yet — step 2 hasn't been entered.
        $this->assertDatabaseCount('approval_tasks', 1);
        $this->assertDatabaseHas('approval_tasks', ['auth_role_id' => 350, 'status' => 'pending']);
    }

    public function test_sequential_approval_advances_to_the_next_step()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
            ['roles' => [351 => 'Finance']],
        ]);
        $this->fakeActiveHolders([350 => [10], 351 => [20]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $task = ApprovalTask::where('auth_role_id', 350)->first();

        $decideResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", [
                'decision' => 'approved',
            ]);

        $decideResp->assertOk();
        $this->assertDatabaseHas('approval_tasks', ['id' => $task->id, 'status' => 'approved', 'acted_by_user_id' => 10]);
        $this->assertDatabaseHas('approval_tasks', ['auth_role_id' => 351, 'status' => 'pending']);
        $this->assertDatabaseHas('approval_instances', ['contract_id' => $contractId, 'status' => 'in_progress']);
    }

    public function test_approving_the_final_step_marks_the_contract_approved()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
        ]);
        $this->fakeActiveHolders([350 => [10]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $task = ApprovalTask::where('auth_role_id', 350)->first();

        $decideResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", [
                'decision' => 'approved',
            ]);

        $decideResp->assertOk();
        $this->assertDatabaseHas('approval_instances', ['contract_id' => $contractId, 'status' => 'approved']);
        $this->assertDatabaseHas('contracts', [
            'contract_id' => $contractId,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Approved')->first()->approval_status_id,
        ]);
        $this->getJson("/api/contracts/{$contractId}")->assertOk()
            ->assertJsonPath('data.workflow_status', 'SBSI Review')
            ->assertJsonPath('data.lifecycle_status', 'pending_execution')
            ->assertJsonPath('data.has_pending_approval_workflow', false);
    }

    // ── Parallel steps ──────────────────────────────────────────────────

    public function test_parallel_step_waits_for_all_roles_before_advancing()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['mode' => 'parallel', 'roles' => [350 => 'Sales', 351 => 'Finance']],
        ]);
        $this->fakeActiveHolders([350 => [10], 351 => [20]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $this->assertDatabaseCount('approval_tasks', 2);

        $firstTask = ApprovalTask::where('auth_role_id', 350)->first();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$firstTask->id}/decision", ['decision' => 'approved'])
            ->assertOk();

        // Still only 1 of 2 approved — instance must still be in_progress,
        // not approved.
        $this->assertDatabaseHas('approval_instances', ['contract_id' => $contractId, 'status' => 'in_progress']);
        $this->assertDatabaseHas('approval_tasks', ['auth_role_id' => 351, 'status' => 'pending']);
    }

    public function test_parallel_step_advances_once_all_roles_approve()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['mode' => 'parallel', 'roles' => [350 => 'Sales', 351 => 'Finance']],
        ]);
        // Same acting user (10) holds both roles here — this test is only
        // verifying the "all tasks approved -> step advances" mechanic,
        // not role-holder enforcement (covered separately below).
        $this->fakeActiveHolders([350 => [10], 351 => [10]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');

        $taskA = ApprovalTask::where('auth_role_id', 350)->first();
        $taskB = ApprovalTask::where('auth_role_id', 351)->first();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$taskA->id}/decision", ['decision' => 'approved'])
            ->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$taskB->id}/decision", ['decision' => 'approved'])
            ->assertOk();

        // Only one step existed, so completing it approves the whole instance.
        $this->assertDatabaseHas('approval_instances', ['contract_id' => $contractId, 'status' => 'approved']);
    }

    public function test_parallel_rejection_short_circuits_and_cancels_sibling_not_rejects_it()
    {
        // Spec 5.4: one rejection in a parallel step fails the step
        // immediately; the other pending task becomes 'canceled', NOT
        // 'rejected'.
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['mode' => 'parallel', 'roles' => [350 => 'Sales', 351 => 'Finance']],
        ]);
        $this->fakeActiveHolders([350 => [10], 351 => [20]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $taskA = ApprovalTask::where('auth_role_id', 350)->first();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$taskA->id}/decision", [
                'decision' => 'rejected', 'comment' => 'Pricing terms need revision',
            ])
            ->assertOk();

        $this->assertDatabaseHas('approval_tasks', ['auth_role_id' => 350, 'status' => 'rejected']);
        $this->assertDatabaseHas('approval_tasks', ['auth_role_id' => 351, 'status' => 'canceled']);
        $this->assertDatabaseHas('approval_instances', ['contract_id' => $contractId, 'status' => 'revisions_required']);
        $this->assertDatabaseHas('contracts', [
            'contract_id' => $contractId,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Rejected')->first()->approval_status_id,
            'rejection_reason' => 'Pricing terms need revision',
        ]);
    }

    // ── Resubmission (spec 5.5 / decision #11) ─────────────────────────

    public function test_resubmit_mode_restart_creates_a_new_run_starting_at_step_one()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $workflow = $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
            ['roles' => [351 => 'Finance']],
        ]);
        $workflow->update(['resubmit_mode' => 'restart']);
        $this->fakeActiveHolders([350 => [10], 351 => [20]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload(['serial_number' => 'SN-RESTART']));
        $contractId = $createResp->json('data.contract_db_id');
        $task = ApprovalTask::where('auth_role_id', 350)->first();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", ['decision' => 'rejected'])
            ->assertOk();

        $this->assertDatabaseHas('approval_instances', ['contract_id' => $contractId, 'run_number' => 1, 'status' => 'revisions_required']);

        // Owner edits and resubmits.
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->putJson("/api/contracts/{$contractId}", $this->createContractPayload([
                'serial_number' => 'SN-RESTART', 'description' => 'Revised description',
            ]))
            ->assertOk();

        $this->assertDatabaseCount('approval_instances', 2);
        $this->assertDatabaseHas('approval_instances', ['contract_id' => $contractId, 'run_number' => 2, 'status' => 'in_progress']);
        // New run's task is for role 350 (step 1) again, not role 351.
        $newRunInstanceId = ApprovalInstance::where('contract_id', $contractId)->where('run_number', 2)->first()->id;
        $this->assertDatabaseHas('approval_tasks', ['instance_id' => $newRunInstanceId, 'auth_role_id' => 350, 'status' => 'pending']);
    }

    public function test_resubmit_mode_resume_at_rejected_restarts_at_the_rejected_step()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $workflow = $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
            ['roles' => [351 => 'Finance']],
        ]);
        $workflow->update(['resubmit_mode' => 'resume_at_rejected']);
        // Same acting user (10) holds both roles — this test verifies the
        // resume-at-rejected resubmission mechanic, not role enforcement.
        $this->fakeActiveHolders([350 => [10], 351 => [10]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload(['serial_number' => 'SN-RESUME']));
        $contractId = $createResp->json('data.contract_db_id');

        // Approve step 1, reject at step 2.
        $step1Task = ApprovalTask::where('auth_role_id', 350)->first();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$step1Task->id}/decision", ['decision' => 'approved'])
            ->assertOk();

        $step2Task = ApprovalTask::where('auth_role_id', 351)->first();
        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$step2Task->id}/decision", ['decision' => 'rejected'])
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->putJson("/api/contracts/{$contractId}", $this->createContractPayload([
                'serial_number' => 'SN-RESUME', 'description' => 'Revised',
            ]))
            ->assertOk();

        $newRunInstance = ApprovalInstance::where('contract_id', $contractId)->where('run_number', 2)->first();
        $this->assertNotNull($newRunInstance);
        // Resumes at the rejected step (role 351), skipping step 1 entirely.
        $this->assertDatabaseHas('approval_tasks', ['instance_id' => $newRunInstance->id, 'auth_role_id' => 351, 'status' => 'pending']);
        $this->assertDatabaseMissing('approval_tasks', ['instance_id' => $newRunInstance->id, 'auth_role_id' => 350]);
    }

    public function test_rejection_history_from_the_prior_run_is_preserved_not_erased()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [['roles' => [350 => 'Regulatory']]]);
        $this->fakeActiveHolders([350 => [10]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload(['serial_number' => 'SN-HIST']));
        $contractId = $createResp->json('data.contract_db_id');
        $task = ApprovalTask::where('auth_role_id', 350)->first();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", [
                'decision' => 'rejected', 'comment' => 'Needs more detail',
            ])->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->putJson("/api/contracts/{$contractId}", $this->createContractPayload(['serial_number' => 'SN-HIST']))
            ->assertOk();

        // The rejected task from run 1 still exists, untouched.
        $this->assertDatabaseHas('approval_tasks', [
            'id' => $task->id, 'status' => 'rejected', 'comment' => 'Needs more detail',
        ]);
        $this->assertDatabaseCount('approval_instances', 2);
    }

    // ── Empty role / on-hold (decision #4 / #12) ───────────────────────

    public function test_step_with_zero_active_holders_is_put_on_hold_and_admin_is_notified()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [['roles' => [999 => 'Nobody Holds This']]]);
        $this->fakeActiveHolders([999 => []]); // zero holders

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());

        $createResp->assertStatus(201);
        $this->assertDatabaseHas('approval_tasks', ['auth_role_id' => 999, 'status' => 'on_hold']);

        Http::assertSent(function ($request) {
            if ($request->url() === 'http://notification:8000/api/internal/push') {
                $payload = json_decode($request->body(), true);
                return $payload['notification_type'] === 'approval_role_empty'
                    && $payload['target_roles'] === 'Admin';
            }
            return true;
        });
    }

    // ── Role-holding enforcement ────────────────────────────────────────

    public function test_user_who_does_not_hold_the_task_role_cannot_decide_it()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [['roles' => [350 => 'Regulatory']]]);
        $this->fakeActiveHolders([350 => [999]]); // only user 999 holds it, not 10

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $task = ApprovalTask::where('auth_role_id', 350)->first();

        // Acting user is 10 (per fakeAuth), who does NOT hold role 350.
        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", ['decision' => 'approved']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('approval_tasks', ['id' => $task->id, 'status' => 'pending']);
    }

    public function test_a_delegate_who_now_holds_the_role_can_decide_it()
    {
        // Option C: contract-management never stores delegation data, it
        // just asks "who holds role R now" — a delegate shows up in that
        // list transparently, same as a primary holder.
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 77]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [['roles' => [350 => 'Regulatory']]]);
        // Primary holder is 10, but 77 is also returned (as if delegated).
        $this->fakeActiveHolders([350 => [10, 77]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $task = ApprovalTask::where('auth_role_id', 350)->first();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", ['decision' => 'approved']);

        $response->assertOk();
        $this->assertDatabaseHas('approval_tasks', ['id' => $task->id, 'status' => 'approved', 'acted_by_user_id' => 77]);
    }

    public function test_cannot_decide_an_already_actioned_task()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
            ['roles' => [351 => 'Finance']],
        ]);
        $this->fakeActiveHolders([350 => [10], 351 => [20]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $task = ApprovalTask::where('auth_role_id', 350)->first();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", ['decision' => 'approved'])
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", ['decision' => 'approved']);

        $response->assertStatus(409);
    }

    // ── Legacy endpoint guard ────────────────────────────────────────────

    public function test_legacy_update_status_endpoint_is_blocked_for_engine_managed_contracts()
    {
        config(['services.features.workflow_engine_enabled' => true, 'services.features.enforce_sod' => false]);
        $this->fakeAuth(['role' => 'Manager', 'id' => 99]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [['roles' => [350 => 'Manager']]]);
        $this->fakeActiveHolders([350 => [99]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $createResp->assertCreated()->assertJsonPath('data.has_pending_approval_workflow', true);

        $this->getJson('/api/contracts')->assertOk()->assertJsonPath('data.0.has_pending_approval_workflow', true);
        $this->getJson('/api/contract-requests')->assertOk()->assertJsonPath('data.0.has_pending_approval_workflow', true);
        $this->getJson("/api/contracts/{$contractId}")->assertOk()->assertJsonPath('data.has_pending_approval_workflow', true);
        $this->getJson("/api/contracts/{$contractId}/approval-progress")->assertOk()->assertJsonCount(1, 'data.my_tasks');

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->patchJson("/api/contracts/{$contractId}/status", ['approval_status' => 'Approved']);

        $response->assertStatus(409);
        // Status must remain Pending — the legacy endpoint made no change.
        $this->assertDatabaseHas('contracts', [
            'contract_id' => $contractId,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
        ]);
    }

    public function test_legacy_update_status_endpoint_still_works_for_non_engine_contracts()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Manager', 'id' => 99]);
        $this->makeCategory(); // no active workflow attached

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $createResp->assertCreated()->assertJsonPath('data.has_pending_approval_workflow', false);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->patchJson("/api/contracts/{$contractId}/status", ['approval_status' => 'Rejected', 'rejection_reason' => 'test']);

        $response->assertOk();
    }

    // ── Phase 6: approval-progress enrichment for the Visual Tracker ────

    public function test_approval_progress_returns_enriched_tracker_fields()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 42, 'first_name' => 'Creator', 'last_name' => 'Person']);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
            ['roles' => [351 => 'Sales']],
        ]);
        $this->fakeActiveHolders([350 => [42], 351 => [43]]);
        $this->fakeRoleDescribe([
            350 => ['exists' => true, 'deleted' => false, 'current_name' => 'Regulatory', 'was_renamed' => true, 'name_history' => [['old_name' => 'Regulator']]],
            351 => ['exists' => true, 'deleted' => false, 'current_name' => 'Sales', 'was_renamed' => false, 'name_history' => []],
        ]);
        $this->fakeUsersBatch([42 => 'Creator Person']);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson("/api/contracts/{$contractId}/approval-progress");

        $response->assertOk();
        $response->assertJsonPath('data.engine', true);
        $response->assertJsonPath('data.contract_created_by', 'Creator Person');
        $this->assertNotNull($response->json('data.contract_created_at'));

        $run = $response->json('data.runs.0');
        $this->assertNotNull($run['current_step_id']);
        $this->assertCount(2, $run['groups']);

        $task = $run['groups'][0]['tasks'][0];
        $this->assertEquals('pending', $task['status']);
        $this->assertTrue($task['is_current']);
        $this->assertFalse($task['role_deleted']);
        $this->assertEquals('Regulator', $task['role_renamed_from']);
        $this->assertNull($task['delegate_name']);
        // Step 2 hasn't been entered yet — no tasks, and no current flag.
        // Its step definitions are still exposed so the tracker can render
        // the future step as pending/locked.
        $this->assertCount(0, $run['groups'][1]['tasks']);
        $futureRoles = $run['groups'][1]['roles'];
        $this->assertCount(1, $futureRoles);
        $this->assertEquals(351, $futureRoles[0]['auth_role_id']);
        $this->assertEquals('Sales', $futureRoles[0]['role_name']);
        $this->assertFalse($futureRoles[0]['role_deleted']);
    }

    public function test_approval_progress_marks_deleted_role_and_resolves_delegate_name()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Sales', 'id' => 10]);
        $category = $this->makeCategory();
        $this->makeActiveWorkflow($category, [
            ['roles' => [350 => 'Regulatory']],
        ]);
        $this->fakeActiveHolders([350 => [10]]);

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');
        $task = ApprovalTask::where('auth_role_id', 350)->first();

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson("/api/contracts/{$contractId}/approval-tasks/{$task->id}/decision", ['decision' => 'approved'])
            ->assertOk();

        // Simulate a delegation-backed decision + a role deleted afterwards.
        $task->refresh()->update(['acted_via_delegation' => true]);
        $this->fakeRoleDescribe([
            350 => ['exists' => false, 'deleted' => true, 'current_name' => null, 'was_renamed' => false, 'name_history' => []],
        ]);
        $this->fakeUsersBatch([10 => 'Alex Reyes']);

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson("/api/contracts/{$contractId}/approval-progress");

        $response->assertOk();
        $taskJson = $response->json('data.runs.0.groups.0.tasks.0');
        $this->assertEquals('approved', $taskJson['status']);
        $this->assertTrue($taskJson['role_deleted']);
        $this->assertNull($taskJson['role_renamed_from']);
        $this->assertEquals('Alex Reyes', $taskJson['delegate_name']);
    }

    public function test_approval_progress_reports_no_engine_for_legacy_contracts()
    {
        config(['services.features.workflow_engine_enabled' => true]);
        $this->fakeAuth(['role' => 'Manager', 'id' => 99]);
        $this->makeCategory(); // no active workflow attached

        $createResp = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/contracts', $this->createContractPayload());
        $contractId = $createResp->json('data.contract_db_id');

        $response = $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson("/api/contracts/{$contractId}/approval-progress");

        $response->assertOk();
        $response->assertJsonPath('data.engine', false);
        $this->assertCount(0, $response->json('data.runs'));
    }
}
