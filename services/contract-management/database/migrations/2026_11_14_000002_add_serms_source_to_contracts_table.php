<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('source_system', 50)->nullable()->after('prs_activity_id')->index();
            $table->string('source_reference_id', 100)->nullable()->after('source_system');
            $table->json('supporting_expense_summary')->nullable()->after('source_reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropIndex(['source_system']);
            $table->dropColumn(['source_system', 'source_reference_id', 'supporting_expense_summary']);
        });
    }
};
