<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_reads', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('is_favorite');
        });
    }

    public function down(): void
    {
        Schema::table('notification_reads', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });
    }
};
