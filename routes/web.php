<?php

use App\Http\Controllers\BusinessTypeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TariffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('layouts/app');
});

//Route::middleware('auth')->group(function () {
    Route::group(['prefix' => 'client'], function () {
        Route::get('/', [ClientController::class, 'index'])->name('client.index');
        Route::get('/create', [ClientController::class, 'create'])->name('client.create');
        Route::post('/store', [ClientController::class, 'store'])->name('client.store');
        Route::get('edit/{client}', [ClientController::class, 'edit'])->name('client.edit');
        Route::patch('update/{client}', [ClientController::class, 'update'])->name('client.update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->name('client.destroy');
    });

    Route::group(['prefix' => 'organization'], function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('organization.index');
        Route::get('/create', [OrganizationController::class, 'create'])->name('organization.create');
        Route::post('/store', [OrganizationController::class, 'store'])->name('organization.store');
        Route::get('edit/{organization}', [OrganizationController::class, 'edit'])->name('organization.edit');
        Route::patch('update/{organization}', [OrganizationController::class, 'update'])->name('organization.update');
        Route::delete('/{organization}', [OrganizationController::class, 'destroy'])->name('organization.destroy');
    });

    Route::group(['prefix' => 'business-type'], function () {
        Route::get('/', [BusinessTypeController::class, 'index'])->name('business_type.index');
        Route::post('/store', [BusinessTypeController::class, 'store'])->name('business_type.store');
        Route::patch('/{businessType}', [BusinessTypeController::class, 'update'])->name('business_type.update');
        Route::delete('/{businessType}', [BusinessTypeController::class, 'destroy'])->name('business_type.delete');
    });

    Route::group(['prefix' => 'tariff'], function () {
        Route::get('/', [TariffController::class, 'index'])->name('tariff.index');
        Route::post('/store', [TariffController::class, 'store'])->name('tariff.store');
        Route::patch('/{tariff}', [TariffController::class, 'update'])->name('tariff.update');
        Route::delete('/{tariff}', [TariffController::class, 'destroy'])->name('tariff.delete');
    });


    Route::group(['prefix' => 'sale'], function () {
        Route::get('/', [SaleController::class, 'index'])->name('sale.index');
        Route::post('/store', [SaleController::class, 'store'])->name('sale.store');
        Route::patch('/{sale}', [SaleController::class, 'update'])->name('sale.update');
        Route::delete('/{sale}', [SaleController::class, 'destroy'])->name('sale.delete');
    });

    Route::group(['prefix' => 'partner'], function () {
        Route::get('/', [PartnerController::class, 'index'])->name('partner.index');
        Route::post('/store', [PartnerController::class, 'store'])->name('partner.store');
        Route::get('/create', [PartnerController::class, 'create'])->name('partner.create');
        Route::get('edit/{partner}', [PartnerController::class, 'edit'])->name('partner.edit');
        Route::patch('/{partner}', [PartnerController::class, 'update'])->name('partner.update');
        Route::delete('/{partner}', [PartnerController::class, 'destroy'])->name('partner.delete');
    });

//});

require __DIR__.'/auth.php';
