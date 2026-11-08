<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create approval statuses lookup table
        if (!Schema::hasTable('contract_approval_statuses')) {
            Schema::create('contract_approval_statuses', function (Blueprint $table) {
                $table->bigIncrements('approval_status_id');
                $table->string('status_name');
            });

            DB::table('contract_approval_statuses')->insert([
                ['status_name' => 'Pending'],
                ['status_name' => 'Approved'],
                ['status_name' => 'Rejected'],
            ]);
        }

        $pendingId = DB::table('contract_approval_statuses')
            ->where('status_name', 'Pending')
            ->value('approval_status_id');

        // 2. Add approval_status_id (nullable for backfill, then tighten)
        if (!Schema::hasColumn('contracts', 'approval_status_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('approval_status_id')->nullable()->after('status_id');
            });

            DB::table('contracts')->update(['approval_status_id' => $pendingId]);

            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('approval_status_id')->nullable(false)->change();
                $table->foreign('approval_status_id')
                      ->references('approval_status_id')
                      ->on('contract_approval_statuses');
            });
        }

        // 3-6. Rename status_id → workflow_status_id
        if (Schema::hasColumn('contracts', 'status_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign('contracts_status_id_foreign');
                }
            });

            Schema::table('contracts', function (Blueprint $table) {
                $table->renameColumn('status_id', 'workflow_status_id');
            });

            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('workflow_status_id')->nullable()->change();
                $table->foreign('workflow_status_id')
                      ->references('status_id')
                      ->on('contract_statuses');
            });

            DB::table('contracts')->update(['workflow_status_id' => null]);
        }
    }

    public function down(): void
    {
        $notarizedId = DB::table('contract_statuses')
            ->where('status_name', 'Notarized PDF')
            ->value('status_id') ?? 1;

        Schema::table('contracts', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('contracts_workflow_status_id_foreign');
            }
        });

        DB::table('contracts')->update(['workflow_status_id' => $notarizedId]);

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_status_id')->nullable(false)->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('workflow_status_id', 'status_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('status_id')->references('status_id')->on('contract_statuses');
        });

        Schema::table('contracts', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('contracts_approval_status_id_foreign');
            }
            $table->dropColumn('approval_status_id');
        });

        Schema::dropIfExists('contract_approval_statuses');
    }
};
