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
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('contract_id');
            $table->index('notification_date');
            $table->index(['user_id', 'is_read'], 'idx_notifications_user_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['contract_id']);
            $table->dropIndex(['notification_date']);
            $table->dropIndex('idx_notifications_user_read');
        });
    }
};
