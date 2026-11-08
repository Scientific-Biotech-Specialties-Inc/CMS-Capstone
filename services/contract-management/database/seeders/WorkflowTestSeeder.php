<?php

namespace Database\Seeders;

use App\Models\ContractCategory;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepRole;
use Illuminate\Database\Seeder;

/**
 * Test-only workflow seeds (Phase 6 tracker testing / Playwright).
 *
 * Creates ONE active single-step sequential workflow per active contract
 * category (locked decision #9: one workflow per contract type), with the
 * single step assigned to the auth-module role holding the test users
 * (default: the `Manager` role — id 559 in the live auth DB — whose holder
 * is sales-marketing-manager@example.com).
 *
 * The role lives in auth-module's separate database, which this service
 * cannot join to — so the numeric ID comes from the environment:
 *   SEED_WORKFLOW_ROLE_ID   (default 559)
 *   SEED_WORKFLOW_ROLE_NAME (default 'Manager', stored as the name snapshot)
 *
 * Idempotent: categories that already have an active workflow are skipped,
 * so re-running never duplicates. Run explicitly (NOT registered in
 * DatabaseSeeder, to keep default seeds clean):
 *   docker compose exec contract-management php artisan db:seed \
 *     --class="Database\Seeders\WorkflowTestSeeder"
 *
 * Note: the engine starts instances on contract CREATION. Existing
 * (already Approved) contracts are untouched — create a new contract of a
 * seeded category to see the live tracker + approval-progress data.
 */
class WorkflowTestSeeder extends Seeder
{
    public function run(): void
    {
        // Auth role IDs are re-issued on a fresh auth DB seed — confirm the
        // current Manager ID (auth roles table) if the default mismatches.
        $roleId = (int) env('SEED_WORKFLOW_ROLE_ID', 132);
        $roleName = (string) env('SEED_WORKFLOW_ROLE_NAME', 'Manager');

        if ($roleId <= 0) {
            $this->command->error('SEED_WORKFLOW_ROLE_ID is not set to a valid auth role ID. Aborting.');
            return;
        }

        $categories = ContractCategory::where('is_active', true)->orderBy('category_id')->get();

        if ($categories->isEmpty()) {
            $this->command->warn('No active contract categories found. Run ContractLookupSeeder first.');
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($categories as $category) {
            $hasActive = Workflow::where('contract_type_id', $category->category_id)
                ->where('status', 'active')
                ->exists();

            if ($hasActive) {
                $skipped++;
                continue;
            }

            $workflow = Workflow::create([
                'contract_type_id' => $category->category_id,
                'name' => "Test Approval — {$category->category_name}",
                'status' => 'active',
                'resubmit_mode' => 'restart',
                'created_by' => 1,
            ]);

            $step = WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'order_index' => 1,
                'mode' => 'sequential',
            ]);

            WorkflowStepRole::create([
                'step_id' => $step->id,
                'auth_role_id' => $roleId,
                'role_name_snapshot' => $roleName,
            ]);

            $created++;
        }

        $this->command->info("WorkflowTestSeeder: created {$created} active test workflow(s) (single {$roleName} step), skipped {$skipped} categorie(s) with an existing active workflow.");
    }
}
