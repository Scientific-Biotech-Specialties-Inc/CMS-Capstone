<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id('audit_id');
            $table->string('action', 50);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->jsonb('old_data')->nullable();
            $table->jsonb('new_data')->nullable();
            $table->timestamp('performed_at')->useCurrent();
            $table->string('user_name', 255)->nullable();
            $table->string('user_email', 255)->nullable();
            $table->string('user_role', 100)->nullable();
            $table->string('user_department', 100)->nullable()->index();

            $table->index('performed_at');
            $table->index('entity_type');
            $table->index('action');
            $table->index(['user_id', 'performed_at']);
            $table->index(['user_department', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
