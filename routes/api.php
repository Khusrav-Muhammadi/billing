<?php

use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\ProfileController;
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

Route::get('clients-balance', [\App\Http\Controllers\ClientController::class, 'getBalance']);
Route::get('createInvoice', [\App\Http\Controllers\ClientController::class, 'createInvoice']);
Route::middleware('auth.basic')->group(function () {
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('clients', [\App\Http\Controllers\API\ClientController::class, 'index']);
    Route::get('clients/nfr', [\App\Http\Controllers\API\ClientController::class, 'getNfr']);
    Route::post('clients/store', [\App\Http\Controllers\API\ClientController::class, 'store']);
    Route::get('partners', [\App\Http\Controllers\API\ClientController::class, 'getPartners']);
    Route::get('countries', [\App\Http\Controllers\API\ClientController::class, 'getCountries']);
    Route::get('currencies', [\App\Http\Controllers\API\ClientController::class, 'getCurrencies']);
    Route::get('businessType', [\App\Http\Controllers\API\ClientController::class, 'getBusinessTypes']);
    Route::get('sale', [\App\Http\Controllers\API\ClientController::class, 'sale']);
    Route::get('clients/{client}', [\App\Http\Controllers\API\ClientController::class, 'show']);
    Route::patch('clients/{client}', [\App\Http\Controllers\API\ClientController::class, 'update']);
    Route::post('clients/activation/{client}', [\App\Http\Controllers\API\ClientController::class, 'activation']);
    Route::post('clients/create-transaction/{id}', [\App\Http\Controllers\API\ClientController::class, 'createTransaction']);
    Route::get('clients/getOrganizations/{client}', [\App\Http\Controllers\API\ClientController::class, 'getOrganizations']);
    Route::get('clients/getTransactions/{client}', [\App\Http\Controllers\API\ClientController::class, 'getTransactions']);
    Route::get('clients/getHistory/{client}', [\App\Http\Controllers\API\ClientController::class, 'getHistory']);

    Route::post('organizations/access/{organization}', [\App\Http\Controllers\API\OrganizationController::class, 'access']);
    Route::apiResource('organizations', \App\Http\Controllers\API\OrganizationController::class)->except(['store']);
    Route::post('organizations/{client}', [\App\Http\Controllers\API\OrganizationController::class, 'store']);
    Route::post('organizations/addPack/{id}', [\App\Http\Controllers\API\OrganizationController::class, 'addPack']);
    Route::delete('organizations/delete-pack/{id}', [\App\Http\Controllers\API\OrganizationController::class, 'deletePack']);

    Route::get('partner-request', [\App\Http\Controllers\API\PartnerRequestController::class, 'index']);
    Route::post('partner-request', [\App\Http\Controllers\API\PartnerRequestController::class, 'store']);
    Route::get('partner-request/{partnerRequest}', [\App\Http\Controllers\API\PartnerRequestController::class, 'show']);
    Route::patch('partner-request/{partnerRequest}', [\App\Http\Controllers\API\PartnerRequestController::class, 'update']);
    Route::patch('partner-request/change-status/{partnerRequest}', [\App\Http\Controllers\API\PartnerRequestController::class, 'changeStatus']);

    Route::get('dashboard', [\App\Http\Controllers\API\DashBoardController::class, 'index']);

    Route::group(['prefix' => 'profile'], function () {
        Route::patch('/change-password', [ProfileController::class, 'changePasswordApi']);
        Route::patch('/{user}', [ProfileController::class, 'updateApi']);
    });
});

Route::post('client/activity/{subdomain}', [\App\Http\Controllers\ClientController::class, 'updateActivity']);

Route::options('/{any}', function (Request $request) {
    return response()->json(['status' => 'ok'], 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
    ]);
})->where('any', '.*');
Route::post('sendRequest', [\App\Http\Controllers\API\SiteApplicationController::class, 'store']);

Route::post('login', [\App\Http\Controllers\API\AuthController::class, 'login']);

Route::get('organization/tariff-info/{organization}', [\App\Http\Controllers\API\OrganizationController::class, 'tariffInfo']);
Route::get('organization/legal-info/{organization}', [\App\Http\Controllers\API\OrganizationController::class, 'getLegalInfo']);
Route::post('organization/legal-info/{organization}', [\App\Http\Controllers\API\OrganizationController::class, 'addLegalInfo']);
Route::post('add-organization', [\App\Http\Controllers\API\OrganizationController::class, 'addOrganization']);

Route::post('payment/alif/webhook/change-tariff', [\App\Http\Controllers\API\ClientController::class, 'webhookChangeTariff']);

Route::get('tariff-difference', [\App\Http\Controllers\API\ClientController::class, 'countDifference']);
Route::get('tariff', [\App\Http\Controllers\TariffController::class, 'getTariffByCurrency']);
Route::get('t/tariff', [\App\Http\Controllers\TariffController::class, 'tariff']);
Route::post('change-tariff', [\App\Http\Controllers\API\ClientController::class, 'changeTariff']);


Route::post('/payment', [PaymentController::class, 'createInvoice']);
Route::post('/payment/webhook/{provider}', [PaymentController::class, 'handleWebhook']);
Route::get('partners/{country_id}', [\App\Http\Controllers\API\ClientController::class, 'getPartnersByCountry']);
