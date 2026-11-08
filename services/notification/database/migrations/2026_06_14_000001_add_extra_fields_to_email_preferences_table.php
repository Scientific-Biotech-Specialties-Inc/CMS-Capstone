<?php

declare(strict_types=1);

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
        Schema::table('email_preferences', function (Blueprint $table) {
            $table->boolean('system_alerts_enabled')->default(true)->after('contract_expiry_alerts');
            $table->boolean('sms_notifications_enabled')->default(false)->after('system_alerts_enabled');
            $table->boolean('login_alerts_enabled')->default(true)->after('sms_notifications_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'system_alerts_enabled',
                'sms_notifications_enabled',
                'login_alerts_enabled',
            ]);
        });
    }
};
