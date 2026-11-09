<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExpiringContracts extends Command
{
    protected $signature   = 'contracts:check-expiry';
    protected $description = 'Push expiry notifications for contracts expiring in 90, 30, or 1 day(s).';

    public function handle(NotificationService $notif): int
    {
        $today = Carbon::today();

        $contracts = Contract::dateMonitored()->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $today->copy()->addDays(90))
            ->get();

        $pushed = 0;

        foreach ($contracts as $contract) {
            $date     = $contract->end_date->format('M d, Y');
            $serial   = $contract->serial_number ?? "Contract #{$contract->contract_id}";
            $daysLeft = (int) $today->diffInDays($contract->end_date);

            // Pick the single most urgent threshold that applies — a contract
            // expiring tomorrow also falls within the 30/90-day windows, but
            // should only trigger one notification, not all three.
            if ($daysLeft <= 1) {
                $type   = 'expiry_1';
                $phrase = 'is expiring tomorrow';
            } elseif ($daysLeft <= 30) {
                $type   = 'expiry_30';
                $phrase = 'is less than a month away from expiring';
            } else {
                $type   = 'expiry_90';
                $phrase = 'is less than 90 days away from expiring';
            }

            $message      = "Contract {$serial} {$phrase} (on {$date}).";
            $salesMessage = "Your contract {$serial} {$phrase} (on {$date}).";

            // Role groupings mirror the frontend router's layout access rules (router/index.ts):
            // /manager → Manager · /sales → Sales, Employee, Finance
            $okBroadcast = $notif->push($contract->contract_id, $type, $message, 'Admin,Manager');
            $okSales     = $notif->push($contract->contract_id, $type, $salesMessage, 'Sales,Employee,Finance', (int) $contract->created_by);

            if ($okBroadcast || $okSales) {
                $pushed++;
                $this->line("  Sent [{$type}] for contract {$serial} (new or existing)");
            }
        }

        $this->info("Done. {$pushed} notification(s) pushed.");
        return Command::SUCCESS;
    }
}
