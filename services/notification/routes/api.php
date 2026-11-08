<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Internal push — authenticated by X-Internal-Secret header (no user token needed)
Route::middleware(['internal.secret'])->group(function () {
    Route::post('/internal/push', [NotificationController::class, 'push']);
    Route::post('/internal/high-risk-approval-escalation', [\App\Http\Controllers\Api\V1\Internal\HighRiskEscalationController::class, 'push']);

    // Feature 4: Analytics — descriptive metrics snapshot for analytics-service.
    Route::get('/internal/metrics/notifications', [\App\Http\Controllers\Api\V1\Internal\InternalMetricsController::class, 'notifications']);
});

// User-facing — authenticated by Bearer JWT token
Route::middleware(['auth.internal'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/{id}/state', [NotificationController::class, 'updateState']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::post('/notifications/batch-delete', [NotificationController::class, 'batchDestroy']);
    
    // Email Preferences & Logs
    Route::get('/email-preferences', App\Http\Controllers\Api\V1\EmailPreferences\ShowController::class);
    Route::put('/email-preferences', App\Http\Controllers\Api\V1\EmailPreferences\UpdateController::class);
    Route::get('/email-logs', App\Http\Controllers\Api\V1\EmailLogs\IndexController::class);

    // System Configuration
    Route::get('/system-config', App\Http\Controllers\Api\V1\SystemConfig\ShowController::class);
    Route::put('/system-config', App\Http\Controllers\Api\V1\SystemConfig\UpdateController::class);
});

