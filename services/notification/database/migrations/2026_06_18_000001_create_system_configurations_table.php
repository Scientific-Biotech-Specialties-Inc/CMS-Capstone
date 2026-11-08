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
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->boolean('email_notifs_enabled')->default(true);
            $table->boolean('in_app_notifs_enabled')->default(true);
            $table->boolean('contract_expiry_alerts')->default(true);
            $table->boolean('approval_alerts')->default(true);
            $table->boolean('renewal_reminders')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configurations');
    }
};
