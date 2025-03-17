<?php

use App\Http\Controllers\BusinessTypeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PartnerRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
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
    return view('auth.login');
});

Route::get('/dashboard', [\App\Http\Controllers\DashBoardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::group(['prefix' => 'client'], function () {
        Route::get('/', [ClientController::class, 'index'])->name('client.index');
        Route::get('/create', [ClientController::class, 'create'])->name('client.create');
        Route::post('/store', [ClientController::class, 'store'])->name('client.store');
        Route::get('edit/{client}', [ClientController::class, 'edit'])->name('client.edit');
        Route::get('show/{client}', [ClientController::class, 'show'])->name('client.show');
        Route::patch('update/{client}', [ClientController::class, 'update'])->name('client.update');
        Route::post('/{client}', [ClientController::class, 'activation'])->name('client.activation');
        Route::post('/create-transaction/{client}', [ClientController::class, 'createTransaction'])->name('client.createTransaction');
    });

    Route::group(['prefix' => 'organization'], function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('organization.index');
        Route::post('/store/{client}', [OrganizationController::class, 'store'])->name('organization.store');
        Route::get('show/{organization}', [OrganizationController::class, 'show'])->name('organization.show');
        Route::get('edit/{organization}', [OrganizationController::class, 'edit'])->name('organization.edit');
        Route::patch('update/{organization}', [OrganizationController::class, 'update'])->name('organization.update');
        Route::delete('/{organization}', [OrganizationController::class, 'destroy'])->name('organization.destroy');
        Route::post('access/{organization}', [OrganizationController::class, 'access'])->name('organization.access');
        Route::post('addPack/{organization}', [OrganizationController::class, 'addPack'])->name('organization.addPack');
        Route::delete('delete-pack/{organizationPack}', [OrganizationController::class, 'deletePack'])->name('organization.pack.destroy');
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

    Route::group(['prefix' => 'pack'], function () {
        Route::get('/', [PackController::class, 'index'])->name('pack.index');
        Route::post('/store', [PackController::class, 'store'])->name('pack.store');
        Route::patch('/{pack}', [PackController::class, 'update'])->name('pack.update');
        Route::delete('/{pack}', [PackController::class, 'destroy'])->name('pack.delete');
    });

    Route::group(['prefix' => 'partner'], function () {
        Route::get('/', [PartnerController::class, 'index'])->name('partner.index');
        Route::post('/store', [PartnerController::class, 'store'])->name('partner.store');
        Route::get('/create', [PartnerController::class, 'create'])->name('partner.create');
        Route::get('edit/{partner}', [PartnerController::class, 'edit'])->name('partner.edit');
        Route::patch('/{partner}', [PartnerController::class, 'update'])->name('partner.update');
        Route::delete('/{partner}', [PartnerController::class, 'destroy'])->name('partner.delete');
    });

    Route::group(['prefix' => 'request'], function () {
        Route::get('/', [PartnerRequestController::class, 'index'])->name('partner-request.index');
        Route::get('/{partnerRequest}', [PartnerRequestController::class, 'edit'])->name('partner-request.edit');
        Route::patch('/{partnerRequest}', [PartnerRequestController::class, 'update'])->name('partner-request.update');
        Route::post('/reject/{partnerRequest}', [PartnerRequestController::class, 'reject'])->name('partner-request.reject');
        Route::get('/approve/{partnerRequest}', [PartnerRequestController::class, 'approve'])->name('partner-request.approve');
    });

    Route::group(['prefix' => 'report'], function () {
        Route::get('/income', [ReportController::class, 'income'])->name('report.income');
    });

    Route::group(['prefix' => 'profile'], function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.index');
        Route::patch('/change-password', [ProfileController::class, 'changePassword'])->name('profile.changePassword');
        Route::patch('/{user}', [ProfileController::class, 'update'])->name('profile.update');
    });

});

require __DIR__.'/auth.php';
