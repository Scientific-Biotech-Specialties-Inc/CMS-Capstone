<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Models\ContractApproval;
use App\Models\ContractCategory;
use App\Models\ContractApprovalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The SLA escalation command is a no-op while US-023's gate is disabled
 * (default), since no contract_approvals rows are created in that state.
 * Kept scheduled and functional so it resumes working immediately if the
 * gate is re-enabled — no wiring changes needed.
 */
class CheckHighRiskApprovalSlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ContractCategory::firstOrCreate(['category_name' => 'Service Agreement']);
        ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending']);
        \Illuminate\Support\Facades\DB::table('contract_regions')->insertOrIgnore([
            ['region_name' => 'Luzon'],
        ]);
    }

    public function test_command_no_ops_and_sends_no_escalation_while_gate_disabled()
    {
        Http::fake();

        $this->artisan('contracts:check-high-risk-approval-sla')
            ->expectsOutputToContain('High-risk approval gate is disabled')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_command_escalates_overdue_approvals_when_gate_re_enabled()
    {
        config(['services.features.high_risk_approval_gate_enabled' => true]);

        Http::fake([
            'http://notification:8000/api/internal/high-risk-approval-escalation' => Http::response(['message' => 'queued'], 202),
        ]);

        $contract = Contract::create([
            'category_id'        => ContractCategory::first()->category_id,
            'approval_status_id' => ContractApprovalStatus::firstOrCreate(['status_name' => 'Pending'])->approval_status_id,
            'bp_name'            => 'Test Vendor',
            'item_code'          => 'ITM-SLA',
            'description'        => 'Desc',
            'serial_number'      => 'SN-SLA-1',
            'sbu_number'         => 'SBU-123',
            'region_id'          => \Illuminate\Support\Facades\DB::table('contract_regions')->first()->region_id,
            'start_date'         => '2026-01-01',
            'end_date'           => '2026-12-31',
            'created_by'         => 42,
        ]);

        $approval = ContractApproval::create([
            'contract_id' => $contract->contract_id,
            'risk_level'  => 'high',
            'flagged_at'  => now()->subHours(30),
            'sla_due_at'  => now()->subHours(6),
        ]);

        $this->artisan('contracts:check-high-risk-approval-sla')->assertExitCode(0);

        $this->assertNotNull($approval->fresh()->escalated_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'high-risk-approval-escalation'));
    }
}
