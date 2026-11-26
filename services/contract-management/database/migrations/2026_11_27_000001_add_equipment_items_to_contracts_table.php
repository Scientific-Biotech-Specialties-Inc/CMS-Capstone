<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contracts', 'equipment_items')) {
            Schema::table('contracts', function (Blueprint $table): void {
                $table->json('equipment_items')->nullable()->after('model');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contracts', 'equipment_items')) {
            Schema::table('contracts', function (Blueprint $table): void {
                $table->dropColumn('equipment_items');
            });
        }
    }
};
