<?php

use App\Http\Controllers\SchemaHealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health/schema', [SchemaHealthController::class, 'check']);
