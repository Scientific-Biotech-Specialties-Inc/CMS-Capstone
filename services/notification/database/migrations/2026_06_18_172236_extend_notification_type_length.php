<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Widens notification_type from VARCHAR(20) to VARCHAR(50) so that
     * longer types like 'contract_status_updated' (24 chars) and
     * 'manager_approval_request' (24 chars) can be stored.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notification_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notification_type', 20)->change();
        });
    }
};
