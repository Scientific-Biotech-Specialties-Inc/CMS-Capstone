<?php

use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

// Feature 4: Analytics — user-facing, bearer-token authenticated, Admin/Manager-only.
Route::middleware(['auth.internal'])->group(function () {
    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('/analytics/diagnostics', [AnalyticsController::class, 'diagnostics']);
    Route::get('/analytics/predictive', [AnalyticsController::class, 'predictive']);
    Route::post('/analytics/refresh', [AnalyticsController::class, 'refresh']);
});
