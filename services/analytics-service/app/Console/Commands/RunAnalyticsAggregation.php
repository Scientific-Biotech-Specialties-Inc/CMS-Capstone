<?php

namespace App\Console\Commands;

use App\Services\DescriptiveAggregationService;
use App\Services\DiagnosticAnalysisService;
use Illuminate\Console\Command;

/**
 * Feature 4: scheduled aggregation pass. Pulls descriptive metrics from
 * every source service, writes aggregated_metrics, then runs the
 * algorithmic diagnostic breakdowns (+ optional Gemini narrative) on top of
 * that freshly aggregated data.
 */
class RunAnalyticsAggregation extends Command
{
    protected $signature   = 'analytics:aggregate';
    protected $description = 'Pull descriptive metrics from every source service and run diagnostic analysis on top of them.';

    public function handle(DescriptiveAggregationService $descriptive, DiagnosticAnalysisService $diagnostic): int
    {
        $written = $descriptive->runAll();
        $this->info('Descriptive aggregation written: ' . json_encode($written));

        $insights = $diagnostic->runAll();
        $this->info(count($insights) . ' diagnostic insight(s) generated.');

        return Command::SUCCESS;
    }
}
