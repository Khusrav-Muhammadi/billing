<?php

use App\Http\Controllers\API\InvoiceController;
use App\Http\Controllers\AccountController;

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BusinessTypeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConnectedClientServiceController;
use App\Http\Controllers\DayClosingController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PartnerRequestController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TariffController;
use App\Http\Controllers\V2\OrganizationV2Controller;
use App\Http\Controllers\V2\SubdomainController;
use Illuminate\Http\Request;
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

// Preview page for PDF generation (accessed by Browsershot)
Route::get('/commercial-offer-preview', [\App\Http\Controllers\API\CommercialOfferController::class, 'previewPage']);

Route::get('/commercial-offer', function (Request $request) {
    return view('commercial-offer', [
        'client' => $request->query('client', 'ИП "Расулов Амир Давронович"'),
        'manager' => $request->query('manager', 'Расулов Амир'),
        'date' => $request->query('date', now()->format('d.m.Y')),
    ]);
})->name('commercial-offer');

Route::get('blade', function () {
    return view('mail.pdf');
});
Route::get('{invoice}/download', [InvoiceController::class, 'download'])
    ->name('invoice.download');
Route::get('/dashboard', [\App\Http\Controllers\DashBoardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('kp/config', [ConnectedClientServiceController::class, 'config'])->name('kp.config');
    // Backward-compatible alias: some builds still call /api/kp/config from the iframe.
    Route::get('api/kp/config', [ConnectedClientServiceController::class, 'config'])->name('api.kp.config');
    Route::get('kp/clients', [ConnectedClientServiceController::class, 'clients'])->name('kp.clients');
    Route::get('kp/partners', [ConnectedClientServiceController::class, 'partners'])->name('kp.partners');

    Route::group(['prefix' => 'client'], function () {
        Route::get('/', [ClientController::class, 'index'])->name('client.index');
        Route::get('/create', [ClientController::class, 'create'])->name('client.create');
        Route::post('/store', [ClientController::class, 'store'])->name('client.store');
        Route::get('edit/{client}', [ClientController::class, 'edit'])->name('client.edit');
        Route::get('show/{client}', [ClientController::class, 'show'])->name('client.show');
        Route::patch('update/{client}', [ClientController::class, 'update'])->name('client.update');
        Route::post('/{client}', [ClientController::class, 'activation'])->name('client.activation');
        Route::post('/deleteAll/{client}', [ClientController::class, 'deleteAll'])->name('client.deleteAll');
        Route::post('/create-transaction/{client}', [ClientController::class, 'createTransaction'])->name('client.createTransaction');
    });

    Route::group(['prefix' => 'sub_domain'], function () {
        Route::get('/', [SubdomainController::class, 'index'])->name('sub_domain.index');
        Route::get('/create', [SubdomainController::class, 'create'])->name('sub_domain.create');
        Route::post('/store', [SubdomainController::class, 'store'])->name('sub_domain.store');
        Route::get('edit/{client}', [SubdomainController::class, 'edit'])->name('sub_domain.edit');
        Route::get('show/{client}', [SubdomainController::class, 'show'])->name('sub_domain.show');
        Route::patch('update/{client}', [SubdomainController::class, 'update'])->name('sub_domain.update');
        Route::post('/{client}', [SubdomainController::class, 'activation'])->name('sub_domain.activation');
        Route::post('/deleteAll/{client}', [SubdomainController::class, 'deleteAll'])->name('sub_domain.deleteAll');
        Route::post('/create-transaction/{client}', [SubdomainController::class, 'createTransaction'])->name('sub_domain.createTransaction');
    });

    Route::group(['prefix' => 'payment'], function () {
        Route::get('/', [\App\Http\Controllers\PaymentController::class, 'index'])->name('payment.index');
        Route::get('/approve-invoice/{invoice}', [\App\Http\Controllers\PaymentController::class, 'approveInvoice'])->name('payment.approve-invoice');
    });

    Route::group(['prefix' => 'client-payment'], function () {
        Route::get('/', [\App\Http\Controllers\ClientPaymentController::class, 'index'])->name('client-payment.index');
        Route::post('/', [\App\Http\Controllers\ClientPaymentController::class, 'store'])->name('client-payment.create');
        Route::get('/invoice/{payment}', [\App\Http\Controllers\ClientPaymentController::class, 'invoice'])->name('client-payment.invoice');
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

    Route::group(['prefix' => 'organization_v2'], function () {
        Route::get('/', [OrganizationV2Controller::class, 'index'])->name('organization_v2.index');
        Route::get('/demo', [OrganizationV2Controller::class, 'demo'])->name('organization_v2.demo');
        // Some old UI links used GET /organization_v2/store; keep it working by redirecting to client create page.
        Route::get('/store', fn () => redirect()->route('client.create'));
        Route::post('/store/', [OrganizationV2Controller::class, 'store'])->name('organization_v2.store');
        Route::get('show/{organization}', [OrganizationV2Controller::class, 'show'])->name('organization_v2.show');
        Route::get('edit/{organization}', [OrganizationV2Controller::class, 'edit'])->name('organization_v2.edit');
        Route::patch('update/{organization}', [OrganizationV2Controller::class, 'update'])->name('organization_v2.update');
        Route::delete('/{organization}', [OrganizationV2Controller::class, 'destroy'])->name('organization_v2.destroy');
        Route::post('access/{organization}', [OrganizationV2Controller::class, 'access'])->name('organization_v2.access');
        Route::post('addPack/{organization}', [OrganizationV2Controller::class, 'addPack'])->name('organization_v2.addPack');
        Route::delete('delete-pack/{organizationPack}', [OrganizationV2Controller::class, 'deletePack'])->name('organization_v2.pack.destroy');
    });

    Route::group(['prefix' => 'application'], function () {
        Route::get('/', [ApplicationController::class, 'index'])->name('application.index');
        Route::post('/store', [ApplicationController::class, 'store'])->name('application.store');
        Route::post('/{offer}/statuses', [ApplicationController::class, 'storeOfferStatus'])->name('application.status.store');
        Route::post('/kp/store', [ApplicationController::class, 'storeCommercialOffer'])->name('application.kp.store');
        Route::post('/kp/client/store', [ApplicationController::class, 'storeCommercialOfferClient'])->name('application.kp.client.store');
        Route::get('/kp/connection-context/{organization}', [ApplicationController::class, 'getConnectionContext'])->name('application.kp.connection-context');
        Route::get('/kp/{offer}', [ApplicationController::class, 'getCommercialOfferState'])->name('application.kp.show');
        Route::get('/show/{offer}', [ApplicationController::class, 'showCommercialOffer'])->name('application.show');
        Route::get('/create', [ApplicationController::class, 'create'])->name('application.create');
        Route::get('/create/connection', [ApplicationController::class, 'createConnection'])->name('application.create.connection');
        Route::get('/create/connection-extra-services', [ApplicationController::class, 'createConnectionExtraServices'])->name('application.create.connection-extra-services');
        Route::get('/create/renewal', [ApplicationController::class, 'createRenewal'])->name('application.create.renewal');
        Route::get('/create/renewal-no-changes', [ApplicationController::class, 'createRenewalNoChanges'])->name('application.create.renewal-no-changes');
        Route::get('edit/{id}', [ApplicationController::class, 'edit'])->name('application.edit');
        Route::patch('/{id}', [ApplicationController::class, 'update'])->name('application.update');
        Route::delete('/{id}', [ApplicationController::class, 'destroy'])->name('application.delete');
    });

    Route::group(['prefix' => 'day-closing'], function () {
        Route::get('/', [DayClosingController::class, 'index'])->name('day-closing.index');
        Route::get('/create', [DayClosingController::class, 'create'])->name('day-closing.create');
        Route::post('/store', [DayClosingController::class, 'store'])->name('day-closing.store');
        Route::get('/{dayClosing}', [DayClosingController::class, 'show'])->name('day-closing.show');
    });

    Route::group(['prefix' => 'connected-client-service'], function () {
        Route::get('/', [ConnectedClientServiceController::class, 'index'])->name('connected_client_service.index');
        Route::get('/', [ConnectedClientServiceController::class, 'index'])->name('connected_client_service.index');

    });


    Route::group(['prefix' => 'account'], function () {
        Route::get('/', [AccountController::class, 'index'])->name('account.index');
        Route::post('/store', [AccountController::class, 'store'])->name('account.store');
        Route::patch('/{account}', [AccountController::class, 'update'])->name('account.update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->name('account.delete');
    });

    Route::group(['prefix' => 'currency-rate'], function () {
        Route::get('/', [\App\Http\Controllers\CurrencyRateController::class, 'index'])->name('currency-rate.index');
        Route::get('/{currency}', [\App\Http\Controllers\CurrencyRateController::class, 'show'])->name('currency-rate.show');
        Route::post('/{currency}/store', [\App\Http\Controllers\CurrencyRateController::class, 'store'])->name('currency-rate.store');
        Route::patch('/{currencyRate}', [\App\Http\Controllers\CurrencyRateController::class, 'update'])->name('currency-rate.update');
        Route::delete('/{currencyRate}', [\App\Http\Controllers\CurrencyRateController::class, 'destroy'])->name('currency-rate.delete');
    });

    Route::group(['prefix' => 'business-type'], function () {
        Route::get('/', [BusinessTypeController::class, 'index'])->name('business_type.index');
        Route::post('/store', [BusinessTypeController::class, 'store'])->name('business_type.store');
        Route::patch('/{businessType}', [BusinessTypeController::class, 'update'])->name('business_type.update');
        Route::delete('/{businessType}', [BusinessTypeController::class, 'destroy'])->name('business_type.delete');
    });

    Route::group(['prefix' => 'site-application'], function () {
        Route::get('/', [\App\Http\Controllers\API\SiteApplicationController::class, 'index'])->name('site-application.index');
        Route::get('/{siteApplication}', [\App\Http\Controllers\API\SiteApplicationController::class, 'destroy'])->name('site-application.delete');
    });

    Route::group(['prefix' => 'tariff'], function () {
        Route::get('/', [TariffController::class, 'index'])->name('tariff.index');
        Route::post('/store', [TariffController::class, 'store'])->name('tariff.store');
        Route::patch('/{tariff}', [TariffController::class, 'update'])->name('tariff.update');
        Route::delete('/{tariff}', [TariffController::class, 'destroy'])->name('tariff.delete');
        Route::get('/{tariff}/included-services', [TariffController::class, 'includedServicesIndex'])->name('tariff.included_services.index');
        Route::post('/{tariff}/included-services', [TariffController::class, 'includedServicesStore'])->name('tariff.included_services.store');
        Route::patch('/{tariff}/included-services/{service}', [TariffController::class, 'includedServicesUpdate'])->name('tariff.included_services.update');
        Route::delete('/{tariff}/included-services/{service}', [TariffController::class, 'includedServicesDestroy'])->name('tariff.included_services.destroy');
        Route::get('/{tariff}/exclusions', [TariffController::class, 'exclusionsIndex'])->name('tariff.exclusions.index');
        Route::post('/{tariff}/exclusions', [TariffController::class, 'exclusionsStore'])->name('tariff.exclusions.store');
        Route::delete('/{tariff}/exclusions/{organization}', [TariffController::class, 'exclusionsDestroy'])->name('tariff.exclusions.destroy');
    });

    Route::group(['prefix' => 'price_list'], function () {
        Route::get('/', [PriceListController::class, 'index'])->name('price_list.index');
        Route::post('/store', [PriceListController::class, 'store'])->name('price_list.store');
        Route::patch('/{price}', [PriceListController::class, 'update'])->name('price_list.update');
        Route::delete('/{price}', [PriceListController::class, 'destroy'])->name('price_list.delete');
    });

    Route::group(['prefix' => 'partner-status'], function () {
        Route::get('/', [\App\Http\Controllers\PartnerStatusController::class, 'index'])->name('partner-status.index');
        Route::post('/store', [\App\Http\Controllers\PartnerStatusController::class, 'store'])->name('partner-status.store');
        Route::patch('/{partnerStatus}', [\App\Http\Controllers\PartnerStatusController::class, 'update'])->name('partner-status.update');
        Route::delete('/{partnerStatus}', [\App\Http\Controllers\PartnerStatusController::class, 'destroy'])->name('partner-status.delete');
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
        Route::post('/partner/addManager', [PartnerController::class, 'addManager'])->name('partner.manager.create');
        Route::post('/{partner}/curators', [PartnerController::class, 'addCurator'])->name('partner.curator.create');
        Route::delete('/{partner}/curators/{curator}', [PartnerController::class, 'removeCurator'])->name('partner.curator.delete');
        Route::post('/partner/addProcent/{user}', [PartnerController::class, 'addProcent'])->name('partner.procent.create');
        Route::post('/partner/addStatus/{user}', [PartnerController::class, 'addStatus'])->name('partner.status.create');
        Route::post('/partner/manager/{user}', [PartnerController::class, 'addManager'])->name('partner.manager.edit');
        Route::patch('/partner/procent/{procent}', [PartnerController::class, 'editProcent'])->name('partner.procent.edit');
        Route::post('/partner/manager/{user}', [PartnerController::class, 'addManager'])->name('partner.manager.edit');
        Route::delete('/partner/manager/{user}', [PartnerController::class, 'destroy'])->name('partner.manager.delete');
        Route::delete('/partner/procent/{procent}', [PartnerController::class, 'destroyProcent'])->name('partner.procent.delete');
        Route::patch('/partner/manager/update/{user}', [PartnerController::class, 'updateManager'])->name('partner.manager.update');
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

require __DIR__ . '/auth.php';
