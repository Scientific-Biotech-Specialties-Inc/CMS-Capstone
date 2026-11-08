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
        // information_schema.statistics is MySQL-specific; the test
        // environment runs this same migration against sqlite (see
        // phpunit.xml), where that table doesn't exist and this query
        // would throw before the migration could even run. Only probe for
        // the index on drivers where this introspection table exists.
        $hasBpNameIndex = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql'
            && \Illuminate\Support\Facades\DB::table('information_schema.statistics')
                ->where('table_schema', \Illuminate\Support\Facades\DB::raw('DATABASE()'))
                ->where('table_name', 'contracts')
                ->where('index_name', 'contracts_bp_name_index')
                ->exists();

        Schema::table('contracts', function (Blueprint $table) use ($hasBpNameIndex) {
            // Drop bp_name index since it cannot be searched effectively when encrypted
            if ($hasBpNameIndex) {
                $table->dropIndex(['bp_name']);
            }
            
            $table->longText('bp_name')->nullable()->change();
            $table->longText('description')->nullable()->change();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->longText('file_path')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('bp_name', 255)->nullable()->change();
            $table->text('description')->nullable()->change();
            
            // Recreate bp_name index
            $table->index('bp_name');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('file_path', 255)->change();
        });
    }
};
