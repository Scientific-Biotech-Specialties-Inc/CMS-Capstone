<?php

use App\Console\Commands\CheckExpiringContracts;
use App\Console\Commands\CheckHighRiskApprovalSla;
use App\Console\Commands\PruneAuditLogs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check every minute; the notification service handles in-app weekly reminders and restricts email alerts to once
Schedule::command(CheckExpiringContracts::class)->everyMinute();

// US-023: escalate High/Critical-risk contract approvals past their 24h SLA window (email-only).
Schedule::command(CheckHighRiskApprovalSla::class)->everyFifteenMinutes();

// Prune audit logs older than retention threshold (default: 90 days) daily at midnight
Schedule::command(PruneAuditLogs::class)->dailyAt('00:00');

