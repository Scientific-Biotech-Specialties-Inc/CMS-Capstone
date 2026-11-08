<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A Workflow is the approval chain template for one contract type
     * (contract_categories row). Only one ACTIVE workflow may exist per
     * contract type at a time (enforced in the application layer via a
     * partial-unique-like check, since MySQL doesn't support a native
     * partial unique index the way Postgres does) — contract types with no
     * active workflow fall back to the legacy single-manager-approval flow
     * untouched by this engine. Drafts can exist freely and are never used
     * for routing.
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_type_id'); // FK to contract_categories.category_id
            $table->string('name');
            $table->enum('status', ['draft', 'active'])->default('draft');
            $table->enum('resubmit_mode', ['restart', 'resume_at_rejected'])->default('restart');
            $table->unsignedBigInteger('created_by')->nullable(); // soft ref: auth-service users.id
            $table->timestamps();

            // restrict (not cascade): contract_categories are retired via
            // is_active, not deleted, so a workflow should never be silently
            // dropped by a category row disappearing underneath it.
            $table->foreign('contract_type_id')->references('category_id')->on('contract_categories')->onDelete('restrict');
            $table->index(['contract_type_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
