<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('contracts', 'contract_code')) {
                $table->string('contract_code', 50)->nullable()->unique()->after('contract_id');
            }
        });

        // Backfill any existing contracts with unique CTR- + 6 alphanumeric characters
        $existing = DB::table('contracts')->whereNull('contract_code')->orWhere('contract_code', '')->get();
        foreach ($existing as $contract) {
            do {
                $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $rand = '';
                for ($i = 0; $i < 6; $i++) {
                    $rand .= $characters[random_int(0, strlen($characters) - 1)];
                }
                $code = 'CTR-' . $rand;
            } while (DB::table('contracts')->where('contract_code', $code)->exists());

            DB::table('contracts')->where('contract_id', $contract->contract_id)->update(['contract_code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'contract_code')) {
                $table->dropUnique(['contract_code']);
                $table->dropColumn('contract_code');
            }
        });
    }
};
