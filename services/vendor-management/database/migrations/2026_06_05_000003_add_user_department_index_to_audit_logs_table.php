<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_department');
            // Composite for the main query pattern: WHERE user_department = ? ORDER BY performed_at DESC
            $table->index(['user_department', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_department']);
            $table->dropIndex(['user_department', 'performed_at']);
        });
    }
};
