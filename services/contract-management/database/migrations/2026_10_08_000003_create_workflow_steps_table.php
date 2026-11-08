<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A WorkflowStep is one position in the approval chain, ordered by
     * order_index. mode = "sequential" (one role, must complete before the
     * next step) or "parallel" (two or more roles via workflow_step_roles,
     * all must act — see 2026_10_08_000004_create_workflow_step_roles_table).
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->onDelete('cascade');
            $table->unsignedInteger('order_index');
            $table->enum('mode', ['sequential', 'parallel'])->default('sequential');
            $table->timestamps();

            $table->unique(['workflow_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
