<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('is_accredited')->default(false)->after('status');
            $table->boolean('is_flagged')->default(false)->after('is_accredited');
            $table->string('default_expense_category', 100)->nullable()->after('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['is_accredited', 'is_flagged', 'default_expense_category']);
        });
    }
};
