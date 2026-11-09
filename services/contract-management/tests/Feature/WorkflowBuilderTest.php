<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\Contract;
use App\Models\ContractApprovalStatus;
use App\Models\ContractCategory;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 4 of the dynamic approval workflow engine plan: Workflow Builder
 * admin endpoints. Admin-only (role:Admin middleware), proxies role data
 * from auth-module, enforces decision #6 (role must hold
 * cms.contracts.view + edit to be activatable) and decision #9 (one
 * active workflow per contract type).
 */
class WorkflowBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('contract_regions')->insertOrIgnore(['region_name' => 'Luzon']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
    }

    private function fakeAdminAuth(): void
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => [
                    'id' => 1,
                    'email' => 'admin@sbsi.com',
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                    'role' => 'Admin',
                    'permissions' => ['cms.contracts.view', 'cms.contracts.edit'],
                    'department' => 'IT',
                ],
            ], 200),
        ]);
    }

    private function fakeRolePermissions(array $roleIdToSlugs): void
    {
        $allPerms = [
            ['id' => 1, 'slug' => 'cms.contracts.view'],
            ['id' => 2, 'slug' => 'cms.contracts.edit'],
            ['id' => 3, 'slug' => 'cms.contracts.approve'],
            ['id' => 4, 'slug' => 'cms.contracts.delete'],
        ];
        $slugToId = collect($allPerms)->mapWithKeys(fn ($p) => [$p['slug'] => $p['id']])->all();

        Http::fake(array_merge(
            [
                'http://auth-service:8000/api/internal/verify-token' => Http::response([
                    'valid' => true,
                    'user' => [
                        'id' => 1, 'email' => 'admin@sbsi.com', 'first_name' => 'Admin', 'last_name' => 'User',
                        'role' => 'Admin', 'permissions' => ['cms.contracts.view', 'cms.contracts.edit'],
                        'department' => 'IT',
                    ],
                ], 200),
                'http://auth-service:8000/api/admin/permissions*' => Http::response($allPerms, 200),
                'http://auth-service:8000/api/internal/roles/350/describe' => Http::response([
                    'exists' => true, 'deleted' => false, 'current_name' => 'Manager',
                ]),
            ],
            collect($roleIdToSlugs)->mapWithKeys(function ($slugs, $roleId) use ($slugToId) {
                $ids = array_map(fn ($slug) => $slugToId[$slug], $slugs);
                return ["http://auth-service:8000/api/admin/roles/{$roleId}/permissions" => Http::response($ids, 200)];
            })->all()
        ));
    }

    private function makeCategory(string $name = 'Service Agreement'): ContractCategory
    {
        return ContractCategory::create(['category_name' => $name, 'is_active' => true]);
    }

    // ── Listing ─────────────────────────────────────────────────────────

    public function test_contract_types_list_shows_active_and_draft_workflow_summaries()
    {
        $this->fakeAdminAuth();
        $category = $this->makeCategory();
        Workflow::create(['contract_type_id' => $category->category_id, 'name' => 'Live', 'status' => 'active']);
        Workflow::create(['contract_type_id' => $category->category_id, 'name' => 'Draft attempt', 'status' => 'draft']);

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->getJson('/api/workflows/contract-types');

        $response->assertOk();
        $data = $response->json('data');
        $entry = collect($data)->firstWhere('contract_type_id', $category->category_id);
        $this->assertNotNull($entry['active_workflow']);
        $this->assertEquals('Live', $entry['active_workflow']['name']);
        $this->assertNotNull($entry['draft_workflow']);
        $this->assertEquals('Draft attempt', $entry['draft_workflow']['name']);
    }

    public function test_non_admin_cannot_access_workflow_builder()
    {
        Http::fake([
            'http://auth-service:8000/api/internal/verify-token' => Http::response([
                'valid' => true,
                'user' => ['id' => 2, 'role' => 'Manager', 'permissions' => ['cms.contracts.view'], 'department' => 'Operations'],
            ], 200),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer manager-token'])
            ->getJson('/api/workflows/contract-types');

        $response->assertStatus(403);
    }

    // ── Save validation (decision #6) ──────────────────────────────────

    public function test_draft_can_be_saved_with_zero_steps()
    {
        $this->fakeAdminAuth();
        $category = $this->makeCategory();

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->postJson('/api/workflows', [
                'contract_type_id' => $category->category_id,
                'name' => 'Empty draft',
                'status' => 'draft',
                'steps' => [],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workflows', ['name' => 'Empty draft', 'status' => 'draft']);
    }

    public function test_activating_a_workflow_with_no_steps_is_rejected()
    {
        $this->fakeAdminAuth();
        $category = $this->makeCategory();

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->postJson('/api/workflows', [
                'contract_type_id' => $category->category_id,
                'name' => 'Empty active attempt',
                'status' => 'active',
                'steps' => [],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('workflows', ['name' => 'Empty active attempt']);
    }

    public function test_activating_with_a_role_missing_required_permissions_is_rejected()
    {
        $this->fakeAdminAuth();
        $this->fakeRolePermissions([350 => ['cms.contracts.approve']]); // missing view+edit
        $category = $this->makeCategory();

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->postJson('/api/workflows', [
                'contract_type_id' => $category->category_id,
                'name' => 'Underpermissioned chain',
                'status' => 'active',
                'steps' => [
                    ['order_index' => 1, 'mode' => 'sequential', 'roles' => [
                        ['auth_role_id' => 350, 'role_name' => 'Manager'],
                    ]],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('workflows', ['name' => 'Underpermissioned chain']);
    }

    public function test_activating_with_a_fully_permissioned_role_succeeds()
    {
        $this->fakeAdminAuth();
        $this->fakeRolePermissions([350 => ['cms.contracts.view', 'cms.contracts.edit', 'cms.contracts.approve']]);
        $category = $this->makeCategory();

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->postJson('/api/workflows', [
                'contract_type_id' => $category->category_id,
                'name' => 'Fully permissioned chain',
                'status' => 'active',
                'steps' => [
                    ['order_index' => 1, 'mode' => 'sequential', 'roles' => [
                        ['auth_role_id' => 350, 'role_name' => 'Manager'],
                    ]],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workflows', ['name' => 'Fully permissioned chain', 'status' => 'active']);
        $this->assertDatabaseHas('workflow_step_roles', ['auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);
    }

    public function test_parallel_step_requires_all_assigned_roles_to_pass_validation()
    {
        $this->fakeAdminAuth();
        $this->fakeRolePermissions([
            350 => ['cms.contracts.view', 'cms.contracts.edit'],
            351 => ['cms.contracts.view'], // missing edit
        ]);
        $category = $this->makeCategory();

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->postJson('/api/workflows', [
                'contract_type_id' => $category->category_id,
                'name' => 'Parallel chain',
                'status' => 'active',
                'steps' => [
                    ['order_index' => 1, 'mode' => 'parallel', 'roles' => [
                        ['auth_role_id' => 350, 'role_name' => 'Sales'],
                        ['auth_role_id' => 351, 'role_name' => 'Finance'],
                    ]],
                ],
            ]);

        $response->assertStatus(422);
    }

    // ── Single active workflow per contract type (decision #9) ────────

    public function test_cannot_activate_a_second_workflow_for_the_same_contract_type()
    {
        $this->fakeAdminAuth();
        $this->fakeRolePermissions([350 => ['cms.contracts.view', 'cms.contracts.edit']]);
        $category = $this->makeCategory();

        Workflow::create(['contract_type_id' => $category->category_id, 'name' => 'Existing live chain', 'status' => 'active']);

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->postJson('/api/workflows', [
                'contract_type_id' => $category->category_id,
                'name' => 'Second active attempt',
                'status' => 'active',
                'steps' => [
                    ['order_index' => 1, 'mode' => 'sequential', 'roles' => [
                        ['auth_role_id' => 350, 'role_name' => 'Manager'],
                    ]],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('workflows', ['name' => 'Second active attempt']);
    }

    public function test_updating_an_already_active_workflow_does_not_conflict_with_itself()
    {
        $this->fakeAdminAuth();
        $this->fakeRolePermissions([350 => ['cms.contracts.view', 'cms.contracts.edit']]);
        $category = $this->makeCategory();

        $workflow = Workflow::create(['contract_type_id' => $category->category_id, 'name' => 'Live chain', 'status' => 'active']);

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->putJson("/api/workflows/{$workflow->id}", [
                'contract_type_id' => $category->category_id,
                'name' => 'Live chain renamed',
                'status' => 'active',
                'steps' => [
                    ['order_index' => 1, 'mode' => 'sequential', 'roles' => [
                        ['auth_role_id' => 350, 'role_name' => 'Manager'],
                    ]],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('workflows', ['id' => $workflow->id, 'name' => 'Live chain renamed', 'status' => 'active']);
    }

    public function test_updating_a_workflow_fully_replaces_its_steps()
    {
        $this->fakeAdminAuth();
        $this->fakeRolePermissions([350 => ['cms.contracts.view', 'cms.contracts.edit']]);
        $category = $this->makeCategory();

        $workflow = Workflow::create(['contract_type_id' => $category->category_id, 'name' => 'Chain', 'status' => 'draft']);
        $oldStep = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 1, 'mode' => 'sequential']);
        WorkflowStepRole::create(['step_id' => $oldStep->id, 'auth_role_id' => 999, 'role_name_snapshot' => 'Old Role']);

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->putJson("/api/workflows/{$workflow->id}", [
                'contract_type_id' => $category->category_id,
                'name' => 'Chain',
                'status' => 'draft',
                'steps' => [
                    ['order_index' => 1, 'mode' => 'sequential', 'roles' => [
                        ['auth_role_id' => 350, 'role_name' => 'New Role'],
                    ]],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('workflow_step_roles', ['auth_role_id' => 999]);
        $this->assertDatabaseHas('workflow_step_roles', ['auth_role_id' => 350, 'role_name_snapshot' => 'New Role']);
        $this->assertDatabaseCount('workflow_steps', 1);
    }

    // ── Duplication (decision #10) ─────────────────────────────────────

    public function test_duplicate_workflow_copies_steps_to_a_new_draft_without_linking_back()
    {
        $this->fakeAdminAuth();
        $sourceCategory = $this->makeCategory('Service Agreement');
        $targetCategory = $this->makeCategory('Supply Contract');

        $source = Workflow::create(['contract_type_id' => $sourceCategory->category_id, 'name' => 'Source chain', 'status' => 'active']);
        $step = WorkflowStep::create(['workflow_id' => $source->id, 'order_index' => 1, 'mode' => 'sequential']);
        WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->postJson("/api/workflows/{$source->id}/duplicate", [
                'target_contract_type_id' => $targetCategory->category_id,
                'name' => 'Copied chain',
            ]);

        $response->assertStatus(201);
        $copyId = $response->json('data.id');

        $this->assertNotEquals($source->id, $copyId);
        $this->assertDatabaseHas('workflows', ['id' => $copyId, 'name' => 'Copied chain', 'status' => 'draft', 'contract_type_id' => $targetCategory->category_id]);
        $this->assertDatabaseCount('workflow_steps', 2); // original + copy
        $this->assertDatabaseCount('workflow_step_roles', 2);

        // Editing the copy's steps must not affect the source.
        WorkflowStep::where('workflow_id', $copyId)->delete();
        $this->assertDatabaseCount('workflow_steps', 1);
        $this->assertEquals($step->id, WorkflowStep::first()->id);
    }

    // ── Deletion guard ──────────────────────────────────────────────────

    public function test_cannot_delete_a_workflow_that_has_been_used_by_a_contract()
    {
        $this->fakeAdminAuth();
        $category = $this->makeCategory();
        $workflow = Workflow::create(['contract_type_id' => $category->category_id, 'name' => 'Used chain', 'status' => 'active']);

        $contract = Contract::create([
            'category_id' => $category->category_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
            'bp_name' => 'Vendor', 'item_code' => 'ITM-1', 'description' => 'd',
            'serial_number' => 'SN-1', 'sbu_number' => 'SBU-1',
            'region_id' => DB::table('contract_regions')->first()->region_id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_by' => 1,
        ]);

        ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 1,
            'status' => 'in_progress',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->deleteJson("/api/workflows/{$workflow->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('workflows', ['id' => $workflow->id]);
    }

    public function test_can_delete_an_unused_draft_workflow()
    {
        $this->fakeAdminAuth();
        $category = $this->makeCategory();
        $workflow = Workflow::create(['contract_type_id' => $category->category_id, 'name' => 'Unused draft', 'status' => 'draft']);

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->deleteJson("/api/workflows/{$workflow->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
    }

    // ── Step-replacement guard (run immutability) ────────────────────────
    // Replacing steps cascade-deletes tasks and orphans live runs — blocked
    // with 409 when any approval run references the workflow. Duplicate
    // the workflow to evolve the chain instead.

    private function makeUsedWorkflow(): array
    {
        $category = $this->makeCategory();
        $workflow = Workflow::create(['contract_type_id' => $category->category_id, 'name' => 'Live chain', 'status' => 'active']);
        $step = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 1, 'mode' => 'sequential']);
        WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);

        $contract = Contract::create([
            'category_id' => $category->category_id,
            'approval_status_id' => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
            'bp_name' => 'Vendor', 'item_code' => 'ITM-1', 'description' => 'd',
            'serial_number' => 'SN-1', 'sbu_number' => 'SBU-1',
            'region_id' => DB::table('contract_regions')->first()->region_id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_by' => 1,
        ]);

        ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 1,
            'status' => 'in_progress',
        ]);

        return [$category, $workflow, $step];
    }

    public function test_cannot_replace_steps_of_workflow_with_live_runs()
    {
        $this->fakeAdminAuth();
        [$category, $workflow, $step] = $this->makeUsedWorkflow();

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->putJson("/api/workflows/{$workflow->id}", [
                'contract_type_id' => $category->category_id,
                'name' => 'Live chain',
                'status' => 'draft',
                'steps' => [
                    ['order_index' => 1, 'mode' => 'sequential', 'roles' => [
                        ['auth_role_id' => 350, 'role_name' => 'Manager'],
                    ]],
                    ['order_index' => 2, 'mode' => 'sequential', 'roles' => [
                        ['auth_role_id' => 351, 'role_name' => 'CEO'],
                    ]],
                ],
            ]);

        $response->assertStatus(409);
        // Original step row untouched — live task references intact.
        $this->assertDatabaseHas('workflow_steps', ['id' => $step->id]);
        $this->assertDatabaseCount('workflow_steps', 1);
    }

    public function test_metadata_only_save_allowed_on_workflow_with_runs()
    {
        $this->fakeAdminAuth();
        [$category, $workflow, $step] = $this->makeUsedWorkflow();

        $response = $this->withHeaders(['Authorization' => 'Bearer admin-token'])
            ->putJson("/api/workflows/{$workflow->id}", [
                'contract_type_id' => $category->category_id,
                'name' => 'Live chain (renamed)',
                'status' => 'draft',
                'resubmit_mode' => 'restart',
                'steps' => [
                    ['order_index' => 1, 'mode' => 'sequential', 'roles' => [
                        ['auth_role_id' => 350, 'role_name' => 'Manager'],
                    ]],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('workflows', ['id' => $workflow->id, 'name' => 'Live chain (renamed)']);
        $this->assertDatabaseHas('workflow_steps', ['id' => $step->id]);
    }

    public function test_activating_a_reviewer_change_preserves_an_ongoing_contract_workflow(): void
    {
        $this->fakeRolePermissions([350 => ['cms.contracts.view', 'cms.contracts.edit']]);
        [$category, $oldVersion, $oldStep] = $this->makeUsedWorkflow();
        $client = $this->withToken('admin-token');
        $client->getJson("/api/workflows/{$oldVersion->id}")->assertOk()->assertJsonPath('data.has_runs', true);

        $role = ['auth_role_id' => 350, 'role_name' => 'Manager'];
        $payload = [
            'contract_type_id' => $category->category_id,
            'name' => 'Updated execution reviewers',
            'steps' => [['order_index' => 1, 'mode' => 'sequential', 'roles' => [$role]]],
            'execution_reviewers' => ['Client Review' => [$role]],
        ];

        $draftId = $client->postJson('/api/workflows', $payload + ['status' => 'draft'])->assertCreated()->json('data.id');
        $client->getJson('/api/workflows/contract-types')->assertOk()
            ->assertJsonPath('data.0.active_workflow.id', $oldVersion->id)
            ->assertJsonPath('data.0.draft_workflow.id', $draftId);

        $replacement = ['status' => 'active', 'replace_active_workflow_id' => $oldVersion->id];
        $client->putJson("/api/workflows/{$draftId}", $payload + $replacement)->assertOk()
            ->assertJsonPath('data.execution_reviewers.Client Review.0.auth_role_id', 350);
        $client->getJson('/api/workflows/contract-types')->assertOk()
            ->assertJsonPath('data.0.active_workflow.id', $draftId)
            ->assertJsonPath('data.0.draft_workflow', null);

        $this->assertNotNull($oldVersion->refresh()->superseded_at);
        $this->assertNull($oldVersion->execution_reviewers);
        $this->assertDatabaseHas('workflow_steps', ['id' => $oldStep->id]);
        $this->assertDatabaseHas('approval_instances', ['workflow_id' => $oldVersion->id, 'status' => 'in_progress']);
        $this->assertEquals($draftId, app(\App\Services\ApprovalEngineService::class)->resolveActiveWorkflow($category->category_id)?->id);

        $client->postJson('/api/workflows', $payload + $replacement)->assertConflict();
        $this->assertDatabaseHas('workflows', ['id' => $draftId, 'status' => 'active']);
    }

    public function test_activating_a_new_version_of_a_used_draft_hides_the_old_draft(): void
    {
        $this->fakeRolePermissions([350 => ['cms.contracts.view', 'cms.contracts.edit']]);
        [$category, $draft] = $this->makeUsedWorkflow();
        $draft->update(['status' => 'draft']);

        $client = $this->withToken('admin-token');
        $newId = $client->postJson('/api/workflows', [
            'contract_type_id' => $category->category_id,
            'name' => 'Reactivated version',
            'status' => 'active',
            'replace_draft_workflow_id' => $draft->id,
            'steps' => [['order_index' => 1, 'mode' => 'sequential', 'roles' => [
                ['auth_role_id' => 350, 'role_name' => 'Manager'],
            ]]],
        ])->assertCreated()->json('data.id');

        $client->getJson('/api/workflows/contract-types')->assertOk()
            ->assertJsonPath('data.0.active_workflow.id', $newId)
            ->assertJsonPath('data.0.draft_workflow', null);
        $this->assertDatabaseHas('approval_instances', ['workflow_id' => $draft->id, 'status' => 'in_progress']);
    }
}
