<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_regions', function (Blueprint $table) {
            $table->bigIncrements('region_id');
            $table->string('region_name');
        });

        DB::table('contract_regions')->insert([
            ['region_name' => 'Luzon'],
            ['region_name' => 'Visayas'],
            ['region_name' => 'Mindanao'],
        ]);

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('region');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE contracts
                SET region_id = (
                    SELECT region_id FROM contract_regions
                    WHERE contract_regions.region_name = contracts.region
                )
            ');
        } else {
            DB::statement('
                UPDATE contracts c
                JOIN contract_regions r ON c.region = r.region_name
                SET c.region_id = r.region_id
            ');
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('region_id')
                  ->references('region_id')
                  ->on('contract_regions')
                  ->onDelete('set null');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('idx_contracts_region');
            $table->dropColumn('region');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('region')->nullable()->after('region_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE contracts
                SET region = (
                    SELECT region_name FROM contract_regions
                    WHERE contract_regions.region_id = contracts.region_id
                )
            ');
        } else {
            DB::statement('
                UPDATE contracts c
                JOIN contract_regions r ON c.region_id = r.region_id
                SET c.region = r.region_name
            ');
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('contracts_region_id_foreign');
            }
            $table->dropColumn('region_id');
        });

        Schema::dropIfExists('contract_regions');
    }
};
