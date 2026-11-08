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
use Tests\TestCase;

/**
 * Phase 3 of the dynamic approval workflow engine plan: schema + model
 * relationships only. No approval routing logic exists yet (that's
 * Phase 5) — this test exists purely to prove the tables and Eloquent
 * relationships are wired correctly before any engine code is built on
 * top of them.
 */
class WorkflowSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([\App\Jobs\SyncContractToMeilisearch::class]);
        DB::table('contract_regions')->insertOrIgnore(['region_name' => 'Luzon']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
    }

    private function makeContract(ContractCategory $category): Contract
    {
        return Contract::create([
            'category_id'          => $category->category_id,
            'approval_status_id'   => ContractApprovalStatus::where('status_name', 'Pending')->first()->approval_status_id,
            'bp_name'               => 'Test Vendor',
            'item_code'             => 'ITM-WF1',
            'description'           => 'Desc',
            'serial_number'         => 'SN-WF-1',
            'sbu_number'            => 'SBU-1',
            'region_id'             => DB::table('contract_regions')->first()->region_id,
            'start_date'            => '2026-01-01',
            'end_date'              => '2026-12-31',
            'created_by'            => 1,
            'notify_manager_count'  => 0,
        ]);
    }

    public function test_only_active_workflow_shows_up_as_the_category_active_workflow()
    {
        $category = ContractCategory::create(['category_name' => 'Service Agreement', 'is_active' => true]);

        $draft = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Draft attempt',
            'status' => 'draft',
        ]);

        $active = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Live chain',
            'status' => 'active',
        ]);

        // activeWorkflow is a hasOne scoped to status=active; re-query fresh
        // from the DB (rather than reusing the $category instance) to avoid
        // relation caching across the two create() calls above.
        $resolved = ContractCategory::find($category->category_id)->activeWorkflow;
        $this->assertNotNull($resolved);
        $this->assertEquals($active->id, $resolved->id);
        $this->assertNotEquals($draft->id, $resolved->id);
    }

    public function test_sequential_step_has_exactly_one_role()
    {
        $category = ContractCategory::create(['category_name' => 'Supply Contract', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Supply chain',
            'status' => 'active',
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'order_index' => 1,
            'mode' => 'sequential',
        ]);

        WorkflowStepRole::create([
            'step_id' => $step->id,
            'auth_role_id' => 350, // Manager, per auth-module seed data
            'role_name_snapshot' => 'Manager',
        ]);

        $this->assertCount(1, $step->workflowStepRoles);
        $this->assertFalse($step->isParallel());
    }

    public function test_parallel_step_supports_multiple_roles()
    {
        $category = ContractCategory::create(['category_name' => 'Equipment Lease', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Parallel chain',
            'status' => 'active',
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'order_index' => 1,
            'mode' => 'parallel',
        ]);

        WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => 351, 'role_name_snapshot' => 'Sales']);
        WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => 352, 'role_name_snapshot' => 'Finance']);

        $this->assertCount(2, $step->workflowStepRoles);
        $this->assertTrue($step->isParallel());
    }

    public function test_same_role_can_appear_in_multiple_steps_of_one_workflow()
    {
        // Decision #5: a role can appear more than once in the same workflow.
        $category = ContractCategory::create(['category_name' => 'Equipment Maintenance', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Repeat-role chain',
            'status' => 'active',
        ]);

        $step1 = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 1, 'mode' => 'sequential']);
        $step2 = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 2, 'mode' => 'sequential']);

        WorkflowStepRole::create(['step_id' => $step1->id, 'auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);
        WorkflowStepRole::create(['step_id' => $step2->id, 'auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);

        $this->assertDatabaseCount('workflow_step_roles', 2);
    }

    public function test_role_cannot_be_duplicated_within_the_same_step()
    {
        $category = ContractCategory::create(['category_name' => 'Partnership Agreement', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Dup-role chain',
            'status' => 'active',
        ]);
        $step = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 1, 'mode' => 'parallel']);

        WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);
    }

    public function test_approval_instance_and_tasks_link_to_contract_and_workflow()
    {
        $category = ContractCategory::create(['category_name' => 'Service Agreement', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Instance chain',
            'status' => 'active',
        ]);
        $step = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 1, 'mode' => 'sequential']);
        WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);

        $contract = $this->makeContract($category);

        $instance = ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 1,
            'current_step_id' => $step->id,
            'status' => 'in_progress',
        ]);

        $task = ApprovalTask::create([
            'instance_id' => $instance->id,
            'step_id' => $step->id,
            'auth_role_id' => 350,
            'status' => 'pending',
        ]);

        $this->assertEquals($contract->contract_id, $instance->contract->contract_id);
        $this->assertEquals($workflow->id, $instance->workflow->id);
        $this->assertEquals($step->id, $instance->currentStep->id);
        $this->assertCount(1, $instance->tasks);
        $this->assertTrue($task->isPending());
    }

    public function test_resubmission_creates_a_new_run_number_preserving_history()
    {
        // Spec 5.5 / decision: rejection history is preserved, a new
        // attempt is a new run — not an overwrite of the old one.
        $category = ContractCategory::create(['category_name' => 'Service Agreement', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Rerun chain',
            'status' => 'active',
        ]);
        $contract = $this->makeContract($category);

        $run1 = ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 1,
            'status' => 'revisions_required',
        ]);

        $run2 = ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 2,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseCount('approval_instances', 2);
        $this->assertEquals('revisions_required', $run1->fresh()->status);
        $this->assertEquals('in_progress', $run2->fresh()->status);
    }

    public function test_canceled_status_is_distinct_from_rejected_for_parallel_tasks()
    {
        // Spec 5.4: one parallel task rejecting cancels the sibling —
        // sibling status is 'canceled', not 'rejected'.
        $category = ContractCategory::create(['category_name' => 'Equipment Lease', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Cancel chain',
            'status' => 'active',
        ]);
        $step = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 1, 'mode' => 'parallel']);
        $contract = $this->makeContract($category);
        $instance = ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 1,
            'current_step_id' => $step->id,
            'status' => 'in_progress',
        ]);

        $rejectedTask = ApprovalTask::create([
            'instance_id' => $instance->id, 'step_id' => $step->id,
            'auth_role_id' => 351, 'status' => 'rejected', 'comment' => 'Pricing issue',
        ]);
        $canceledTask = ApprovalTask::create([
            'instance_id' => $instance->id, 'step_id' => $step->id,
            'auth_role_id' => 352, 'status' => 'canceled',
        ]);

        $this->assertEquals('rejected', $rejectedTask->status);
        $this->assertEquals('canceled', $canceledTask->status);
        $this->assertNotEquals($rejectedTask->status, $canceledTask->status);
    }

    public function test_on_hold_status_available_for_empty_role_scenario()
    {
        // Decision #4/#12: a step whose assigned role has zero active
        // holders puts the task 'on_hold' rather than silently stalling.
        $category = ContractCategory::create(['category_name' => 'Service Agreement', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'On-hold chain',
            'status' => 'active',
        ]);
        $step = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 1, 'mode' => 'sequential']);
        $contract = $this->makeContract($category);
        $instance = ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 1,
            'current_step_id' => $step->id,
            'status' => 'in_progress',
        ]);

        $task = ApprovalTask::create([
            'instance_id' => $instance->id, 'step_id' => $step->id,
            'auth_role_id' => 999, 'status' => 'on_hold',
        ]);

        $this->assertTrue($task->isOnHold());
    }

    public function test_workflow_cannot_be_deleted_while_an_approval_instance_references_it()
    {
        $category = ContractCategory::create(['category_name' => 'Service Agreement', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Protected chain',
            'status' => 'active',
        ]);
        $contract = $this->makeContract($category);
        ApprovalInstance::create([
            'contract_id' => $contract->contract_id,
            'workflow_id' => $workflow->id,
            'run_number' => 1,
            'status' => 'in_progress',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $workflow->delete();
    }

    public function test_deleting_a_workflow_cascades_to_its_steps_and_step_roles()
    {
        $category = ContractCategory::create(['category_name' => 'Service Agreement', 'is_active' => true]);
        $workflow = Workflow::create([
            'contract_type_id' => $category->category_id,
            'name' => 'Deletable draft',
            'status' => 'draft',
        ]);
        $step = WorkflowStep::create(['workflow_id' => $workflow->id, 'order_index' => 1, 'mode' => 'sequential']);
        WorkflowStepRole::create(['step_id' => $step->id, 'auth_role_id' => 350, 'role_name_snapshot' => 'Manager']);

        $workflow->delete();

        $this->assertDatabaseMissing('workflow_steps', ['id' => $step->id]);
        $this->assertDatabaseCount('workflow_step_roles', 0);
    }
}
