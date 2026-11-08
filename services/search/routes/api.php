<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\SearchController;

Route::middleware(['auth.internal'])->group(function () {
    Route::get('/search', SearchController::class)
        ->middleware('permission:cms.contracts.view');
});
