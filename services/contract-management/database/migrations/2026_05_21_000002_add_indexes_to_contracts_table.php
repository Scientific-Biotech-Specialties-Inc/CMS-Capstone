<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addIndexIfMissing('contracts', 'contracts_start_date_index', fn (Blueprint $table) => $table->index('start_date'));
        $this->addIndexIfMissing('contracts', 'contracts_end_date_index', fn (Blueprint $table) => $table->index('end_date'));
        $this->addIndexIfMissing('contracts', 'contracts_created_by_index', fn (Blueprint $table) => $table->index('created_by'));
        $this->addIndexIfMissing('contracts', 'contracts_bp_name_index', fn (Blueprint $table) => $table->index('bp_name'));
        $this->addIndexIfMissing('contracts', 'contracts_serial_number_unique', fn (Blueprint $table) => $table->unique('serial_number'));
        $this->addIndexIfMissing('contracts', 'idx_contracts_status_end_date', fn (Blueprint $table) => $table->index(['status_id', 'end_date'], 'idx_contracts_status_end_date'));
        $this->addIndexIfMissing('contracts', 'idx_contracts_supplier_status', fn (Blueprint $table) => $table->index(['supplier_id', 'status_id'], 'idx_contracts_supplier_status'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('contracts', 'contracts_start_date_index', fn (Blueprint $table) => $table->dropIndex(['start_date']));
        $this->dropIndexIfExists('contracts', 'contracts_end_date_index', fn (Blueprint $table) => $table->dropIndex(['end_date']));
        $this->dropIndexIfExists('contracts', 'contracts_created_by_index', fn (Blueprint $table) => $table->dropIndex(['created_by']));
        $this->dropIndexIfExists('contracts', 'contracts_bp_name_index', fn (Blueprint $table) => $table->dropIndex(['bp_name']));
        $this->dropIndexIfExists('contracts', 'contracts_serial_number_unique', fn (Blueprint $table) => $table->dropUnique(['serial_number']));
        $this->dropIndexIfExists('contracts', 'idx_contracts_status_end_date', fn (Blueprint $table) => $table->dropIndex('idx_contracts_status_end_date'));
        $this->dropIndexIfExists('contracts', 'idx_contracts_supplier_status', fn (Blueprint $table) => $table->dropIndex('idx_contracts_supplier_status'));
    }

    private function addIndexIfMissing(string $tableName, string $indexName, callable $callback): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($callback) {
            $callback($table);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName, callable $callback): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($callback) {
            $callback($table);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $results = DB::select("PRAGMA index_list({$tableName})");
            foreach ($results as $row) {
                if ($row->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }
};
