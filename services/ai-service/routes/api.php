<?php

use App\Http\Controllers\Internal\ContractRiskLevelController;
use App\Http\Controllers\RiskAssessmentController;
use App\Http\Controllers\VendorSuggestionController;
use App\Http\Controllers\OcrExtractionController;
use App\Http\Controllers\ContractGenerationController;
use Illuminate\Support\Facades\Route;

// Internal service-to-service reads — authenticated by X-Internal-Secret header.
Route::middleware(['internal.secret'])->group(function () {
    Route::get('/internal/contracts/{contractId}/risk-level', [ContractRiskLevelController::class, 'show']);

    // Feature 4: Analytics — descriptive metrics snapshot for analytics-service.
    Route::get('/internal/metrics/ai', [\App\Http\Controllers\Internal\InternalMetricsController::class, 'metrics']);
});

// US-026: AI Risk Assessment — user-facing, bearer-token authenticated.
Route::middleware(['auth.internal'])->group(function () {
    Route::get('/contracts/risk-assessment/bulk-levels', [RiskAssessmentController::class, 'bulkLevels']);
    Route::post('/contracts/{contractId}/risk-assessment/scan', [RiskAssessmentController::class, 'scan'])
        ->middleware('throttle:10,1');
    Route::get('/contracts/{contractId}/risk-assessment/summary', [RiskAssessmentController::class, 'summary']);
    Route::get('/contracts/{contractId}/risk-assessment/summary/pdf', [RiskAssessmentController::class, 'summaryPdf']);
    
    // OCR Service - US-OCR
    Route::post('/ocr/extract', [OcrExtractionController::class, 'extract'])
        ->middleware('throttle:10,1');

    // AI Contract Generator
    Route::post('/contracts/generate', [ContractGenerationController::class, 'generate'])
        ->middleware('throttle:10,1');
});

// Feature 3: Vendor AI Suggestions — user-facing, bearer-token authenticated.
Route::middleware(['auth.internal'])->group(function () {
    Route::post('/vendor-suggestions', [VendorSuggestionController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::get('/vendor-suggestions/latest', [VendorSuggestionController::class, 'latest']);
    Route::get('/vendor-suggestions/{id}', [VendorSuggestionController::class, 'show']);
    Route::patch('/vendor-suggestion-candidates/{id}', [VendorSuggestionController::class, 'decide']);
});
