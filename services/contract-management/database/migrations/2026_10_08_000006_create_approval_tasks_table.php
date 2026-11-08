<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An ApprovalTask is one role's work item at one step of one
     * approval_instance. A sequential step produces one task; a parallel
     * step produces one task per workflow_step_role (decision #4: "anyone
     * holding the role" can act — the task is tied to the ROLE, not a
     * specific person, until someone acts on it).
     *
     * Status values:
     *   pending   — step reached, waiting for a holder of the role to act
     *   approved  — a holder of the role approved it
     *   rejected  — a holder of the role rejected it
     *   canceled  — the sibling task's rejection short-circuited this one
     *               (spec 5.4: parallel deadlock — one rejection cancels the
     *               other pending parallel task(s), status is "canceled",
     *               NOT "rejected")
     *   on_hold   — the assigned role currently has zero active holders
     *               (decision #4/#12): the contract waits here, a warning
     *               badge shows, and the admin is notified automatically.
     *
     * acted_via_delegation + delegation_id (soft ref — delegation records
     * live in auth-module, Option C) support the exact wording required by
     * decision/spec 5.7: "Approved by [Delegate Name] on behalf of [Role]".
     * The role name comes from workflow_step_roles.role_name_snapshot; the
     * delegate's name is resolved from acted_by_user_id via auth-module's
     * user lookup at render time, not stored here.
     */
    public function up(): void
    {
        Schema::create('approval_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('approval_instances')->onDelete('cascade');
            $table->foreignId('step_id')->constrained('workflow_steps')->onDelete('cascade');
            $table->unsignedBigInteger('auth_role_id'); // soft ref: auth-module roles.id
            $table->enum('status', ['pending', 'approved', 'rejected', 'canceled', 'on_hold'])->default('pending');
            $table->unsignedBigInteger('acted_by_user_id')->nullable(); // soft ref: auth-service users.id
            $table->boolean('acted_via_delegation')->default(false);
            $table->unsignedBigInteger('delegation_id')->nullable(); // soft ref: auth-module delegations.id
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['instance_id', 'step_id']);
            $table->index(['auth_role_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_tasks');
    }
};
