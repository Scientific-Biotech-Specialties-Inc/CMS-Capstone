<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add an index on the `action` column for faster filtering by action type.
     * Safe guard: skips if the index already exists (shared cms-db scenario).
     */
    public function up(): void
    {
        // Check if the index already exists before adding it, so migrations are safe
        // when two services share the same cms-db and one has already run this migration.
        if (DB::getDriverName() === 'sqlite') {
            $indexExists = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name='audit_logs_action_index'"))->isNotEmpty();
        } else {
            $indexExists = collect(DB::select("SHOW INDEX FROM audit_logs WHERE Key_name = 'audit_logs_action_index'"))->isNotEmpty();
        }

        if (!$indexExists) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index('action'); // for WHERE action = ?
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $indexExists = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name='audit_logs_action_index'"))->isNotEmpty();
        } else {
            $indexExists = collect(DB::select("SHOW INDEX FROM audit_logs WHERE Key_name = 'audit_logs_action_index'"))->isNotEmpty();
        }

        if ($indexExists) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex(['action']);
            });
        }
    }
};
