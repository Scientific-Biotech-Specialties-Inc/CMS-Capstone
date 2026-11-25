<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (!Schema::hasColumn('products', 'prs_product_id')) {
                $table->unsignedBigInteger('prs_product_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('products', 'serial_number')) {
                $table->string('serial_number', 255)->nullable()->after('item_code')->index();
            }
            if (!Schema::hasColumn('products', 'sbu')) {
                $table->unsignedTinyInteger('sbu')->nullable()->after('sbu_number')->index();
            }
            if (!Schema::hasColumn('products', 'brand')) {
                $table->string('brand', 255)->nullable()->after('manufacturer');
            }

            $table->string('category')->nullable()->change();
            $table->text('description')->nullable()->change();

            $table->dropUnique(['item_code']);
            $table->index('item_code');
            $table->unique(['item_code', 'serial_number'], 'products_item_serial_unique');
        });

        // Backfill integer sbu from sbu_number where possible.
        DB::table('products')->whereNull('sbu')->orderBy('id')->each(function (object $row): void {
            if (preg_match('/(\d+)/', (string) ($row->sbu_number ?? ''), $m) === 1) {
                $val = (int) $m[1];
                if ($val >= 0 && $val <= 255) {
                    DB::table('products')->where('id', $row->id)->update(['sbu' => $val]);
                }
            }
        });

        Schema::table('prs_closing_events', function (Blueprint $table): void {
            if (!Schema::hasColumn('prs_closing_events', 'prs_status')) {
                $table->string('prs_status', 50)->nullable()->after('outcome');
            }
            if (!Schema::hasColumn('prs_closing_events', 'mapped_approval_status')) {
                $table->string('mapped_approval_status', 50)->nullable()->after('prs_status');
            }
            if (!Schema::hasColumn('prs_closing_events', 'mapped_workflow_status')) {
                $table->string('mapped_workflow_status', 50)->nullable()->after('mapped_approval_status');
            }
            if (!Schema::hasColumn('prs_closing_events', 'contract_id')) {
                $table->unsignedBigInteger('contract_id')->nullable()->after('mapped_workflow_status')->index();
            }
            if (!Schema::hasColumn('prs_closing_events', 'payload')) {
                $table->json('payload')->nullable()->after('contract_id');
            }
            if (!Schema::hasColumn('prs_closing_events', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prs_closing_events', function (Blueprint $table): void {
            $cols = array_values(array_filter([
                Schema::hasColumn('prs_closing_events', 'prs_status') ? 'prs_status' : null,
                Schema::hasColumn('prs_closing_events', 'mapped_approval_status') ? 'mapped_approval_status' : null,
                Schema::hasColumn('prs_closing_events', 'mapped_workflow_status') ? 'mapped_workflow_status' : null,
                Schema::hasColumn('prs_closing_events', 'contract_id') ? 'contract_id' : null,
                Schema::hasColumn('prs_closing_events', 'payload') ? 'payload' : null,
                Schema::hasColumn('prs_closing_events', 'processed_at') ? 'processed_at' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_item_serial_unique');
            $table->dropIndex(['item_code']);
            $table->unique('item_code');

            $cols = array_values(array_filter([
                Schema::hasColumn('products', 'prs_product_id') ? 'prs_product_id' : null,
                Schema::hasColumn('products', 'serial_number') ? 'serial_number' : null,
                Schema::hasColumn('products', 'sbu') ? 'sbu' : null,
                Schema::hasColumn('products', 'brand') ? 'brand' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
