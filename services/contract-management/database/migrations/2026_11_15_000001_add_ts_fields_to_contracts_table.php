<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'contracts',
        'contract_amendments',
        'contract_version_snapshots',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'sbsi_branch')) {
                    $col = $table->string('sbsi_branch', 255)->nullable();
                    if ($tableName === 'contracts') {
                        $col->index('sbsi_branch');
                    }
                }
                if (!Schema::hasColumn($tableName, 'equipment_name')) {
                    $table->string('equipment_name', 255)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'brand')) {
                    $table->string('brand', 255)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'model')) {
                    $table->string('model', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if ($tableName === 'contracts' && Schema::hasColumn($tableName, 'sbsi_branch')) {
                    $table->dropIndex(['sbsi_branch']);
                }

                $columnsToDrop = array_values(array_filter(
                    ['sbsi_branch', 'equipment_name', 'brand', 'model'],
                    fn (string $column) => Schema::hasColumn($tableName, $column)
                ));

                if ($columnsToDrop !== []) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
