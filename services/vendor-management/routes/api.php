<?php

use Illuminate\Support\Facades\Route;

// Internal webhook — uses X-Internal-Secret, not Bearer token
Route::post('/internal/audit', [\App\Http\Controllers\InternalAuditController::class, 'receive']);

// Internal service-to-service endpoints for ai-service's Vendor AI Suggestions (Feature 3).
Route::middleware(['internal.secret'])->group(function () {
    Route::get('/internal/suppliers', [\App\Http\Controllers\Api\V1\Internal\InternalVendorController::class, 'suppliers']);
    Route::get('/internal/partners', [\App\Http\Controllers\Api\V1\Internal\InternalVendorController::class, 'partners']);
    Route::post('/internal/suppliers', [\App\Http\Controllers\Api\V1\Internal\InternalVendorController::class, 'storeSupplier']);
    Route::post('/internal/partners', [\App\Http\Controllers\Api\V1\Internal\InternalVendorController::class, 'storePartner']);

    // Feature 4: Analytics — descriptive metrics snapshot for analytics-service.
    Route::get('/internal/metrics/vendors', [\App\Http\Controllers\Api\V1\Internal\InternalMetricsController::class, 'vendors']);
});

// Integration Endpoint for retrieving SBSI customer details
Route::get('/integration/customers', [\App\Http\Controllers\Api\V1\IntegrationCustomerController::class, 'index'])
    ->middleware('auth.integration.secret');

Route::middleware(['auth.internal'])->group(function () {

    // Admin User Creation Proxy (Finance Only)
    Route::post('/admin/users', [\App\Http\Controllers\AdminUserProxyController::class, 'store'])
        ->middleware('permission:cms.users.create');

    // Suppliers Routes
    Route::get('/suppliers/search', [\App\Http\Controllers\SupplierController::class, 'search'])
        ->middleware('permission:cms.partners.view');
    Route::get('/suppliers/industries', [\App\Http\Controllers\SupplierController::class, 'industries'])
        ->middleware('permission:cms.partners.view');
    Route::get('/suppliers', [\App\Http\Controllers\SupplierController::class, 'index'])
        ->middleware('permission:cms.partners.view');
    Route::get('/suppliers/{id}', [\App\Http\Controllers\SupplierController::class, 'show'])
        ->middleware('permission:cms.partners.view');
    Route::post('/suppliers', [\App\Http\Controllers\SupplierController::class, 'store'])
        ->middleware('permission:cms.partners.create');
    Route::put('/suppliers/{id}', [\App\Http\Controllers\SupplierController::class, 'update'])
        ->middleware('permission:cms.partners.edit');
    Route::delete('/suppliers/{id}', [\App\Http\Controllers\SupplierController::class, 'destroy'])
        ->middleware('permission:cms.partners.delete');

    // Business Partners Routes
    Route::get('/partners/search', [\App\Http\Controllers\BusinessPartnerController::class, 'search'])
        ->middleware('permission:cms.partners.view');
    Route::get('/partners/industries', [\App\Http\Controllers\BusinessPartnerController::class, 'industries'])
        ->middleware('permission:cms.partners.view');
    Route::get('/partners', [\App\Http\Controllers\BusinessPartnerController::class, 'index'])
        ->middleware('permission:cms.partners.view');
    Route::get('/partners/{id}', [\App\Http\Controllers\BusinessPartnerController::class, 'show'])
        ->middleware('permission:cms.partners.view');
    Route::post('/partners', [\App\Http\Controllers\BusinessPartnerController::class, 'store'])
        ->middleware('permission:cms.partners.create');
    Route::put('/partners/{id}', [\App\Http\Controllers\BusinessPartnerController::class, 'update'])
        ->middleware('permission:cms.partners.edit');
    Route::delete('/partners/{id}', [\App\Http\Controllers\BusinessPartnerController::class, 'destroy'])
        ->middleware('permission:cms.partners.delete');

    // Supplier–Contract Associations
    Route::get('/suppliers/{id}/contracts', \App\Http\Controllers\Api\V1\SupplierContracts\IndexController::class)
        ->middleware('permission:cms.partners.view');
    Route::post('/suppliers/{id}/contracts', \App\Http\Controllers\Api\V1\SupplierContracts\StoreController::class)
        ->middleware('permission:cms.partners.edit');
    Route::delete('/suppliers/{id}/contracts/{contractId}', \App\Http\Controllers\Api\V1\SupplierContracts\DestroyController::class)
        ->middleware('permission:cms.partners.edit');

    // Partner–Contract Associations
    Route::get('/partners/{id}/contracts', \App\Http\Controllers\Api\V1\PartnerContracts\IndexController::class)
        ->middleware('permission:cms.partners.view');
    Route::post('/partners/{id}/contracts', \App\Http\Controllers\Api\V1\PartnerContracts\StoreController::class)
        ->middleware('permission:cms.partners.edit');
    Route::delete('/partners/{id}/contracts/{contractId}', \App\Http\Controllers\Api\V1\PartnerContracts\DestroyController::class)
        ->middleware('permission:cms.partners.edit');

    // Returns all contract IDs already linked to any vendor (used for frontend pre-filtering)
    Route::get('/vendor-contracts/linked-ids', \App\Http\Controllers\Api\V1\LinkedContractIdsController::class)
        ->middleware('permission:cms.partners.view');

    // Vendor eligibility lookup (for integration with other systems)
    Route::get('/vendors/{code}/eligibility', \App\Http\Controllers\Api\V1\VendorEligibilityController::class)
        ->middleware('permission:cms.partners.view');

});
