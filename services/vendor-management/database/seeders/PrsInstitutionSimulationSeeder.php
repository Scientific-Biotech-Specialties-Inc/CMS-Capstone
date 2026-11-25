<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessPartner;
use App\Models\PartnerBranch;
use Illuminate\Database\Seeder;

class PrsInstitutionSimulationSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = [
            [
                'bp_code'        => 'BP-1001',
                'partner_name'   => "St. Luke's Medical Center",
                'industry'       => 'Healthcare',
                'contact_person' => 'Dr. Maria Santos',
                'contact_number' => '09171234501',
                'email'          => 'procurement@stlukes.example.com',
                'address'        => '279 E Rodriguez Sr. Ave, Quezon City, Metro Manila, Philippines',
                'region'         => 'Luzon',
                'class_tier'     => 'Class A',
                'status'         => 'Active',
                'branches'       => [
                    [
                        'location_uid' => 'LOC-1001-QC',
                        'branch_name'  => 'Quezon City Main',
                        'address'      => '279 E Rodriguez Sr. Ave, Quezon City, Metro Manila, Philippines',
                        'region'       => 'Luzon',
                        'sbsi_branch'  => 'Main Office',
                        'is_primary'   => true,
                    ],
                    [
                        'location_uid' => 'LOC-1001-BGC',
                        'branch_name'  => 'Global City (BGC)',
                        'address'      => 'Rizal Drive cor. 32nd St, Taguig, Metro Manila, Philippines',
                        'region'       => 'Luzon',
                        'sbsi_branch'  => 'Main Office',
                        'is_primary'   => false,
                    ],
                ],
            ],
            [
                'bp_code'        => 'BP-1002',
                'partner_name'   => 'Chong Hua Hospital',
                'industry'       => 'Healthcare',
                'contact_person' => 'Engr. Ramon Lim',
                'contact_number' => '09171234502',
                'email'          => 'supply@chonghua.example.com',
                'address'        => 'Fuente Osmena Circle, Cebu City, Cebu, Philippines',
                'region'         => 'Visayas',
                'class_tier'     => 'Class B',
                'status'         => 'Active',
                'branches'       => [
                    [
                        'location_uid' => 'LOC-1002-CEB',
                        'branch_name'  => 'Fuente Osmena Main',
                        'address'      => 'Fuente Osmena Circle, Cebu City, Cebu, Philippines',
                        'region'       => 'Visayas',
                        'sbsi_branch'  => 'Cebu Branch',
                        'is_primary'   => true,
                    ],
                ],
            ],
            [
                'bp_code'        => 'BP-1003',
                'partner_name'   => 'Davao Doctors Hospital',
                'industry'       => 'Diagnostics',
                'contact_person' => 'Carla Mendoza',
                'contact_number' => '09171234503',
                'email'          => 'lab@davaodocs.example.com',
                'address'        => '118 Quirino Ave, Poblacion District, Davao City, Philippines',
                'region'         => 'Mindanao',
                'class_tier'     => 'Class C',
                'status'         => 'Active',
                'branches'       => [
                    [
                        'location_uid' => 'LOC-1003-DVO',
                        'branch_name'  => 'Quirino Ave Main',
                        'address'      => '118 Quirino Ave, Poblacion District, Davao City, Philippines',
                        'region'       => 'Mindanao',
                        'sbsi_branch'  => 'Davao Branch',
                        'is_primary'   => true,
                    ],
                ],
            ],
        ];

        foreach ($institutions as $row) {
            $branches = $row['branches'];
            unset($row['branches']);

            $partner = BusinessPartner::updateOrCreate(
                ['bp_code' => $row['bp_code']],
                $row
            );

            foreach ($branches as $branch) {
                PartnerBranch::updateOrCreate(
                    ['location_uid' => $branch['location_uid']],
                    [
                        ...$branch,
                        'partner_id' => $partner->partner_id,
                        'is_active'  => true,
                    ]
                );
            }
        }
    }
}
