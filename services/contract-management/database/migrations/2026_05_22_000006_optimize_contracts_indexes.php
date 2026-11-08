<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // region_id FK was added in 000005 but the old idx_contracts_region
            // was silently dropped when the 'region' string column was removed.
            $table->index('region_id', 'idx_contracts_region_id');

            // Covers: WHERE created_by = ? ORDER BY created_at DESC
            // The existing (created_by, approval_status_id) covers the filter
            // but not the sort, forcing a filesort on every list request.
            $table->index(['created_by', 'created_at'], 'idx_contracts_owner_created');

            // Note: the bp_name index was automatically removed by MySQL when the
            // column type was changed to longText in the encryption migration —
            // MySQL cannot index TEXT columns. No manual drop needed.
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // idx_contracts_region_id backs the FK constraint on region_id;
            // drop the FK first if a full rollback is needed.
            $table->dropIndex('idx_contracts_owner_created');
        });
    }
};
