<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\ContractApproval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * US-023: 24-hour SLA escalation for pending High/Critical-risk contract
 * approvals. Any contract_approvals row past its sla_due_at with no recorded
 * decision gets escalated_at set once, and an email-only escalation is
 * dispatched to the pending approver via the notification service.
 *
 * DISABLED BY DEFAULT (see config('services.features.high_risk_approval_gate_enabled')).
 * No-ops while the gate is off, since no new contract_approvals rows are
 * created in that state. Still scheduled in routes/console.php so it needs
 * no wiring changes to re-enable — just flip the config flag.
 */
class CheckHighRiskApprovalSla extends Command
{
    protected $signature   = 'contracts:check-high-risk-approval-sla';
    protected $description = 'Escalate (via email) High/Critical-risk contract approvals that have breached the 24-hour SLA window.';

    public function handle(): int
    {
        if (!config('services.features.high_risk_approval_gate_enabled')) {
            $this->info('High-risk approval gate is disabled; skipping SLA escalation check.');
            return Command::SUCCESS;
        }

        $overdue = ContractApproval::whereNull('decision')
            ->whereNull('escalated_at')
            ->where('sla_due_at', '<', now())
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue high-risk approvals.');
            return Command::SUCCESS;
        }

        $notificationUrl = env('NOTIFICATION_SERVICE_URL', 'http://notification:8000/api');
        $secret = env('INTERNAL_SERVICE_SECRET', '');

        $escalated = 0;

        foreach ($overdue as $approval) {
            $contract = Contract::find($approval->contract_id);
            if (!$contract) {
                continue;
            }

            // This schema does not assign a specific approver to a contract
            // ahead of time — any user with the "approve" permission
            // (Manager/Admin) can record the decision. So escalation is
            // broadcast to all Manager-role users, mirroring the existing
            // SendBroadcastExpiryEmails fan-out pattern in the notification
            // service, rather than targeting a single approver_id.
            $hoursOverdue = (int) now()->diffInHours($approval->sla_due_at);

            try {
                $response = Http::withHeaders([
                    'Accept'            => 'application/json',
                    'X-Internal-Secret' => $secret,
                ])->post("{$notificationUrl}/internal/high-risk-approval-escalation", [
                    'target_roles'         => 'Manager',
                    'contract_approval_id' => (int) $approval->id,
                    'contract_id'          => (int) $contract->contract_id,
                    'bp_name'              => (string) $contract->bp_name,
                    'risk_level'           => (string) $approval->risk_level,
                    'hours_overdue'        => $hoursOverdue,
                ]);

                if ($response->successful()) {
                    $approval->update(['escalated_at' => now()]);
                    $escalated++;
                    $this->line("  Escalated approval #{$approval->id} for contract #{$contract->contract_id}");
                } else {
                    Log::warning('High-risk approval escalation push failed', [
                        'approval_id' => $approval->id,
                        'status'      => $response->status(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('High-risk approval escalation connection error', [
                    'approval_id' => $approval->id,
                    'message'     => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. {$escalated} approval(s) escalated.");
        return Command::SUCCESS;
    }
}
