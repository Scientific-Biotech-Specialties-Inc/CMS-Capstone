<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContractMarginsTest extends TestCase
{
    public function test_computes_burn_rate_and_true_net_margin_with_serms_data(): void
    {
        Http::fake([
            '*/api/auth/validate*' => Http::response([
                'user' => ['id' => 1, 'role' => 'Admin'],
            ], 200),
            '*/internal/contracts/active*' => Http::response([
                'data' => [
                    [
                        'contract_id'    => 10,
                        'contract_code'  => 'CTR-ABC123',
                        'client_name'    => 'Acme Biotech',
                        'category'       => 'Service Agreement',
                        'contract_value' => 100000.00,
                        'start_date'     => '2026-01-01',
                        'end_date'       => '2026-12-31',
                    ],
                ],
            ], 200),
            '*/internal/expenses/by-contract*' => Http::response([
                'data' => [
                    [
                        'contract_id'    => 10,
                        'total_expenses' => 25000.00,
                        'breakdown'      => [
                            'travel'        => 10000.00,
                            'spare_parts'   => 10000.00,
                            'field_service' => 5000.00,
                            'other'         => 0.00,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer test-token'])
            ->getJson('/api/analytics/contract-margins');

        $response->assertOk()
            ->assertJsonPath('data.serms_connected', true)
            ->assertJsonPath('data.summary.total_contract_value', 100000)
            ->assertJsonPath('data.summary.total_expenses', 25000)
            ->assertJsonPath('data.summary.total_net_margin', 75000)
            ->assertJsonPath('data.contracts.0.burn_rate_pct', 25)
            ->assertJsonPath('data.contracts.0.true_net_margin', 75000);
    }
}
