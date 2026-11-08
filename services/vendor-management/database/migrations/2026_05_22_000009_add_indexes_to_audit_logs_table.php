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
        try {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index('performed_at');           // for ORDER BY / date filter
                $table->index('user_id');               // for user filter
                $table->index('entity_type');           // for action-type filter
                $table->index(['user_id', 'performed_at']); // composite for user+date queries
            });
        } catch (\Exception $e) {
            // Ignore if indexes already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['performed_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['entity_type']);
            $table->dropIndex(['user_id', 'performed_at']);
        });
    }
};
