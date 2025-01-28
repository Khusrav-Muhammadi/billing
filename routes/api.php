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
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('clients', [\App\Http\Controllers\API\ClientController::class, 'index']);
    Route::get('clients/{client}', [\App\Http\Controllers\API\ClientController::class, 'show']);
    Route::patch('clients/{client}', [\App\Http\Controllers\API\ClientController::class, 'update']);
    Route::get('clients/activation/{client}', [\App\Http\Controllers\API\ClientController::class, 'activation']);
    Route::post('clients/create-transaction/{id}', [\App\Http\Controllers\API\ClientController::class, 'createTransaction']);

    Route::post('organizations/access', [\App\Http\Controllers\API\OrganizationController::class, 'access']);
    Route::apiResource('organizations', \App\Http\Controllers\API\OrganizationController::class)->except(['store']);
    Route::post('organizations/{client_id}', [\App\Http\Controllers\API\OrganizationController::class, 'store']);
    Route::post('organizations/addPack/{id}', [\App\Http\Controllers\API\OrganizationController::class, 'addPack']);

    Route::delete('organizations/delete-pack/{id}', [\App\Http\Controllers\API\OrganizationController::class, 'deletePack']);
    Route::post('partner-request', [\App\Http\Controllers\API\PartnerRequestController::class, 'store']);

});

Route::get('client/activity/{subdomain}', [\App\Http\Controllers\ClientController::class, 'updateActivity']);

Route::post('login', [\App\Http\Controllers\API\AuthController::class, 'login']);
