<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An ApprovalInstance is one contract's run through a workflow.
     * run_number increments on resubmission after a rejection (decision
     * #11/spec 5.5): the previous run's rejection stays in history, a new
     * run starts rather than overwriting it, so the Visual Contract
     * Workflow Tracker can show "Previous attempt #N" alongside the
     * current run.
     */
    public function up(): void
    {
        Schema::create('approval_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->foreignId('workflow_id')->constrained('workflows')->onDelete('restrict');
            $table->unsignedInteger('run_number')->default(1);
            $table->foreignId('current_step_id')->nullable()->constrained('workflow_steps')->onDelete('set null');
            $table->enum('status', ['in_progress', 'approved', 'revisions_required'])->default('in_progress');
            $table->timestamps();

            $table->foreign('contract_id')->references('contract_id')->on('contracts')->onDelete('cascade');
            $table->index(['contract_id', 'run_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_instances');
    }
};
