<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes that support the new split status columns:
     *  - approval_status_id             → filter by Pending / Approved / Rejected
     *  - workflow_status_id             → filter by Notarized PDF / Client Review / SBSI Review
     *  - (approval_status_id, created_at) → contract-requests list ordered by newest
     *  - (created_by, approval_status_id) → sales "my contracts" filtered by status
     *  - category_id                    → filter by category
     *  - region                         → filter by region
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Single-column indexes
            $table->index('approval_status_id',  'idx_contracts_approval_status');
            $table->index('workflow_status_id',  'idx_contracts_workflow_status');
            $table->index('category_id',          'idx_contracts_category');
            $table->index('region',               'idx_contracts_region');

            // Composite — manager contract-requests list (filter by approval + newest first)
            $table->index(['approval_status_id', 'created_at'], 'idx_contracts_approval_created');

            // Composite — sales "my contracts" (filter by owner + status)
            $table->index(['created_by', 'approval_status_id'], 'idx_contracts_owner_approval');

            // Composite — end-date expiry dashboard cards
            $table->index(['approval_status_id', 'end_date'], 'idx_contracts_approval_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('idx_contracts_approval_status');
            $table->dropIndex('idx_contracts_workflow_status');
            $table->dropIndex('idx_contracts_category');
            $table->dropIndex('idx_contracts_region');
            $table->dropIndex('idx_contracts_approval_created');
            $table->dropIndex('idx_contracts_owner_approval');
            $table->dropIndex('idx_contracts_approval_end_date');
        });
    }
};
