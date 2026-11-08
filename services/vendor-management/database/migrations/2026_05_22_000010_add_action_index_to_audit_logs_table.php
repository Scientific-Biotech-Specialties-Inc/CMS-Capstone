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
        try {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index('action'); // for WHERE action = ?
            });
        } catch (\Exception $e) {
            // Ignore if index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex(['action']);
            });
        } catch (\Exception $e) {
            // Ignore if index doesn't exist
        }
    }
};
