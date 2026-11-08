<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days= : The retention threshold in days (default: 90)}';
    protected $description = 'Prune audit logs older than the retention threshold.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('app.audit_log_retention_days', env('AUDIT_LOG_RETENTION_DAYS', 90)));

        if ($days <= 0) {
            $this->error("Invalid retention period: {$days} days. Must be greater than 0.");
            return Command::FAILURE;
        }

        $cutoffDate = Carbon::now()->subDays($days)->startOfDay();

        $this->info("Pruning audit logs older than {$cutoffDate->toDateTimeString()} ({$days} days)...");

        // AuditLog uses 'performed_at' for timestamping
        $query = AuditLog::where('performed_at', '<', $cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No audit logs eligible for pruning.");
            return Command::SUCCESS;
        }

        $deleted = $query->delete();

        Log::info("Audit log retention pruning completed. Pruned {$deleted} records older than {$days} days (before {$cutoffDate->toDateString()}).");
        $this->info("Successfully pruned {$deleted} audit log entries older than {$days} days.");

        return Command::SUCCESS;
    }
}
