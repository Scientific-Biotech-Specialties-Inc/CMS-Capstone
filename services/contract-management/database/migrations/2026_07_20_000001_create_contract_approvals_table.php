<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contract_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id')->index();
            $table->string('risk_level', 20)->index(); // high, critical — the level that triggered the gate.
            $table->unsignedBigInteger('approver_id')->nullable()->index(); // Soft ref: users.id (auth-service).
            $table->text('rationale')->nullable();
            $table->string('decision', 20)->nullable()->index(); // approved, rejected — null while pending.
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('flagged_at'); // When the contract was first flagged High/Critical.
            $table->timestamp('sla_due_at')->index(); // flagged_at + 24 hours.
            $table->timestamp('escalated_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('contract_id')->references('contract_id')->on('contracts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_approvals');
    }
};
