<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notification_type', 20)->nullable()->after('is_read');
            $table->string('target_roles', 255)->nullable()->after('notification_type');
            $table->unsignedBigInteger('user_id')->nullable()->change();

            $table->unique(['contract_id', 'notification_type'], 'uniq_contract_notif_type');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('uniq_contract_notif_type');
            $table->dropColumn(['notification_type', 'target_roles']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
