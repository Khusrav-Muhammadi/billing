<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth.basic')->group(function () {
    Route::get('clients-balance', [\App\Http\Controllers\ClientController::class, 'getBalance']);
    Route::get('clients-by-partner/{partner}', [\App\Http\Controllers\ClientController::class, 'getByPartner']);
});

Route::get('client/activity/{subdomain}', [\App\Http\Controllers\ClientController::class, 'updateActivity']);
