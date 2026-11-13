<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SermsRecurringContractRequestTest extends TestCase
{
    use RefreshDatabase;

    private function secretHeader(): array
    {
        return ['X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET', '')];
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('contract_regions')->insertOrIgnore([
            ['region_name' => 'Luzon'],
        ]);
    }

    public function test_requires_internal_secret(): void
    {
        $this->postJson('/api/internal/contract-requests/from-serms', [])->assertStatus(401);
    }

    public function test_creates_pending_contract_request_from_serms_purchase_history(): void
    {
        $response = $this->withHeaders($this->secretHeader())
            ->postJson('/api/internal/contract-requests/from-serms', [
                'vendor_name'         => 'Metro Calibration Services',
                'tin_number'          => '111-222-333-000',
                'service_description' => 'Quarterly instrument calibration (recurring 4x in SERMS)',
                'occurrence_count'    => 4,
                'total_spend'         => 80000,
                'period_start'        => '2026-01-01',
                'period_end'          => '2026-10-31',
                'source_reference_id' => 'SERMS-REC-42',
                'expense_items'       => [
                    ['date' => '2026-02-10', 'amount' => 20000, 'reference' => 'RMB-101'],
                    ['date' => '2026-05-12', 'amount' => 20000, 'reference' => 'RMB-204'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.source_system', 'SERMS')
            ->assertJsonPath('data.approval_status', 'Pending')
            ->assertJsonPath('data.supporting_expense_summary.occurrence_count', 4);
    }
}
