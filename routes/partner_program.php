<?php

use App\Http\Controllers\API\ClientHealthController;
use App\Http\Controllers\API\PartnerProgramController;
use App\Http\Controllers\API\PortalPasswordResetController;
use Illuminate\Support\Facades\Route;

/*
 * Партнёрский портал partners.shamcrm.com -> биллинг.
 * Все роуты server-to-server: портал сам проверяет, кто пользователь и есть ли у него права.
 */
Route::prefix('partner-program')
    ->middleware(['partners.token', 'throttle:120,1'])
    ->group(function () {
        Route::post('register', [PartnerProgramController::class, 'register']);
        Route::get('partners', [PartnerProgramController::class, 'index']);
        Route::get('partners-report', [PartnerProgramController::class, 'report']);
        Route::get('partners/{partner}', [PartnerProgramController::class, 'show']);
        Route::post('partners/{partner}/approve', [PartnerProgramController::class, 'approve']);
        Route::post('partners/{partner}/reject', [PartnerProgramController::class, 'reject']);
        Route::post('partners/{partner}/percent', [PartnerProgramController::class, 'setPercent']);
        Route::post('partners/{partner}/currency', [PartnerProgramController::class, 'setCurrency']);
        Route::get('partners/{partner}/stats', [PartnerProgramController::class, 'stats']);
        Route::get('partners/{partner}/demos', [PartnerProgramController::class, 'demos']);
        Route::post('partners/{partner}/demos', [PartnerProgramController::class, 'storeDemo']);
        Route::get('partners/{partner}/own-demo', [PartnerProgramController::class, 'ownDemo']);
        Route::get('partners/{partner}/leads', [PartnerProgramController::class, 'leads']);
        // Лимиты own-demo считаются по партнёру внутри PartnerOwnDemoService: здесь все запросы
        // приходят с одного IP портала, и throttle по IP делил бы счётчик между всеми партнёрами.
        Route::post('partners/{partner}/own-demo/link', [PartnerProgramController::class, 'ownDemoLink']);
        Route::post('partners/{partner}/own-demo/confirm', [PartnerProgramController::class, 'ownDemoConfirm']);
        Route::post('partners/{partner}/own-demo/login', [PartnerProgramController::class, 'ownDemoLogin']);

        // «Забыли пароль» в портале: код на почту и новый пароль.
        Route::post('password/forgot', [PortalPasswordResetController::class, 'forgot']);
        Route::post('password/reset', [PortalPasswordResetController::class, 'reset']);

        // Индекс здоровья клиентов (светофор) для админки портала.
        Route::get('client-health', [ClientHealthController::class, 'index']);
        Route::get('client-health/{client}', [ClientHealthController::class, 'history'])->whereNumber('client');
    });
