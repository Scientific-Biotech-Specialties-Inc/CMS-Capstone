<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ContractSimulationSeeder extends Seeder
{
    /**
     * Seed 100 active, long-term contracts:
     *  - All contracts last for 1.5 to 4 years into the future.
     *  - Zero expiring contracts so automated email notifications (Brevo free tier)
     *    are not triggered unnecessarily.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // ── 1. Wipe existing contracts ────────────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('contracts')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── 2. Lookup table IDs ───────────────────────────────────────────────
        $categoryIds      = DB::table('contract_categories')->pluck('category_id')->toArray();
        $approvedStatusId = DB::table('contract_approval_statuses')
                              ->where('status_name', 'Approved')
                              ->value('approval_status_id') ?? 2;
        $statusIds        = DB::table('contract_statuses')->pluck('status_id')->toArray();
        $regionIds        = DB::table('contract_regions')->pluck('region_id')->toArray();

        if (empty($categoryIds)) $categoryIds = [1];
        if (empty($statusIds))   $statusIds   = [1];
        if (empty($regionIds))   $regionIds   = [1];

        $now = now();

        // ── 3. Build 100 long-term contracts ──────────────────────────────────
        $rows = [];

        for ($i = 1; $i <= 100; $i++) {
            $itemCode     = 'ITM-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $serialNumber = 'SN-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . '-' . strtoupper($faker->bothify('??##'));

            // Start date: past 6 months to today
            // End date: 1.5 years (+540 days) to 4 years (+1460 days) in the future
            $startDate = $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d');
            $endDate   = $faker->dateTimeBetween('+18 months', '+48 months')->format('Y-m-d');

            $rows[] = [
                'category_id'        => $categoryIds[array_rand($categoryIds)],
                'approval_status_id' => $approvedStatusId,
                'workflow_status_id' => $statusIds[array_rand($statusIds)],
                'region_id'          => $regionIds[array_rand($regionIds)],
                'bp_name'            => $faker->company(),
                'item_code'          => $itemCode,
                'description'        => $faker->sentence(10),
                'serial_number'      => $serialNumber,
                'sbu_number'         => 'SBU-' . str_pad((string) rand(1, 10), 3, '0', STR_PAD_LEFT),
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'created_by'         => 374,
                'created_at'         => $now->toDateTimeString(),
                'updated_at'         => $now->toDateTimeString(),
            ];
        }

        DB::table('contracts')->insert($rows);

        $this->command->info('Seeded 100 long-term contracts (end dates 1.5 to 4 years out; 0 expiring).');
    }
}
