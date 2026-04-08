<?php

use App\Http\Controllers\API\ClientPaymentController as ApiClientPaymentController;
use App\Http\Controllers\API\CommercialOfferStatusController;
use App\Http\Controllers\API\AccountController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('accounts', [AccountController::class, 'index']);
    Route::get('client-payment/invoice/{payment}', [ApiClientPaymentController::class, 'invoice']);

    Route::get('commercial-foofers/{offer}/statuses', [CommercialOfferStatusController::class, 'index']);
    Route::post('commercial-foofers/{offer}/statuses', [CommercialOfferStatusController::class, 'store']);
    // alias for conventional naming
    Route::get('commercial-offers/{offer}/statuses', [CommercialOfferStatusController::class, 'index']);
    Route::post('commercial-offers/{offer}/statuses', [CommercialOfferStatusController::class, 'store']);
});
