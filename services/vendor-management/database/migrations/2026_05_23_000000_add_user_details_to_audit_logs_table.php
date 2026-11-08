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
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('audit_logs', 'user_name')) {
                    $table->string('user_name', 255)->nullable();
                }
                if (!Schema::hasColumn('audit_logs', 'user_email')) {
                    $table->string('user_email', 255)->nullable();
                }
                if (!Schema::hasColumn('audit_logs', 'user_role')) {
                    $table->string('user_role', 100)->nullable();
                }
                if (!Schema::hasColumn('audit_logs', 'user_department')) {
                    $table->string('user_department', 100)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropColumn(['user_name', 'user_email', 'user_role', 'user_department']);
            });
        }
    }
};
