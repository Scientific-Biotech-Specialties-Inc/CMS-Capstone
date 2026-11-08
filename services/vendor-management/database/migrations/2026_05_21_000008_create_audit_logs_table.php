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
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id('audit_id');
                $table->string('action', 50); // created, updated, deleted
                $table->string('entity_type', 100); // Supplier, BusinessPartner
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->json('old_data')->nullable();
                $table->json('new_data')->nullable();
                $table->timestamp('performed_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
