<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('uniq_contract_notif_type');
            $table->unsignedBigInteger('target_user_id')->nullable()->after('target_roles');
            $table->unique(['contract_id', 'notification_type', 'target_user_id'], 'uniq_contract_notif_type_user');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('uniq_contract_notif_type_user');
            $table->dropColumn('target_user_id');
            $table->unique(['contract_id', 'notification_type'], 'uniq_contract_notif_type');
        });
    }
};
