<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractLookupSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Service Agreement',
            'Partnership Agreement',
            'Supply Contract',
            'Equipment Lease',
            'Equipment Maintenance',
        ];

        foreach ($categories as $name) {
            DB::table('contract_categories')->updateOrInsert(
                ['category_name' => $name],
                ['category_name' => $name]
            );
        }

        $statuses = [
            ['status_name' => 'SBSI Review',    'color_code' => '#EF4444'],
            ['status_name' => 'Client Review',  'color_code' => '#10B981'],
            ['status_name' => 'For Signing',    'color_code' => '#F59E0B'],
            ['status_name' => 'Signed',         'color_code' => '#10B981'],
            ['status_name' => 'Notarized PDF',  'color_code' => '#6B7280'],
        ];

        foreach ($statuses as $status) {
            DB::table('contract_statuses')->updateOrInsert(
                ['status_name' => $status['status_name']],
                $status
            );
        }

        $approvalStatuses = ['Pending', 'Approved', 'Rejected'];

        foreach ($approvalStatuses as $name) {
            DB::table('contract_approval_statuses')->updateOrInsert(
                ['status_name' => $name],
                ['status_name' => $name]
            );
        }

        $regions = ['Luzon', 'Visayas', 'Mindanao'];

        foreach ($regions as $name) {
            DB::table('contract_regions')->updateOrInsert(
                ['region_name' => $name],
                ['region_name' => $name]
            );
        }
    }
}
