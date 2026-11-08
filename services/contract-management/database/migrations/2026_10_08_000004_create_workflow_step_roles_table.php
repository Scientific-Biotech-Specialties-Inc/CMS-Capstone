<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a workflow_step to one identity role (from auth-module). A
     * sequential step has exactly one row here; a parallel step has 2+.
     * auth_role_id is a soft reference — auth-module and contract-management
     * are separate services with separate databases, so there is no real DB
     * foreign key here, only an application-level link resolved via
     * auth-module's internal API at read/action time.
     *
     * role_name_snapshot is cached at save time so the tracker can still
     * show a role's name even if auth-module is briefly unreachable, and so
     * a later live lookup (via /internal/roles/{id}/describe) can be
     * compared against this snapshot to detect "deleted" vs. "renamed"
     * (decision #14 — deleted shows a warning icon, renamed shows an edited
     * icon with the previous name on hover).
     */
    public function up(): void
    {
        Schema::create('workflow_step_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->constrained('workflow_steps')->onDelete('cascade');
            $table->unsignedBigInteger('auth_role_id'); // soft ref: auth-module roles.id
            $table->string('role_name_snapshot');
            $table->timestamps();

            // A role should not be assigned twice within the SAME step
            // (that would be meaningless for a parallel group), but decision
            // #5 explicitly allows the same role to appear in multiple
            // DIFFERENT steps of one workflow, so uniqueness is scoped to
            // (step_id, auth_role_id) only.
            $table->unique(['step_id', 'auth_role_id']);
            $table->index('auth_role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_step_roles');
    }
};
