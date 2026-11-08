<?php

use App\Console\Commands\RunAnalyticsAggregation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Feature 4: refresh descriptive metrics + diagnostic insights hourly.
Schedule::command(RunAnalyticsAggregation::class)->hourly();
