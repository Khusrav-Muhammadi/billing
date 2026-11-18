<?php

use App\Http\Controllers\API\PartnerProgramController;
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
        Route::get('partners/{partner}', [PartnerProgramController::class, 'show']);
        Route::post('partners/{partner}/approve', [PartnerProgramController::class, 'approve']);
        Route::post('partners/{partner}/reject', [PartnerProgramController::class, 'reject']);
        Route::post('partners/{partner}/percent', [PartnerProgramController::class, 'setPercent']);
        Route::get('partners/{partner}/stats', [PartnerProgramController::class, 'stats']);
        Route::get('partners/{partner}/demos', [PartnerProgramController::class, 'demos']);
        Route::post('partners/{partner}/demos', [PartnerProgramController::class, 'storeDemo']);
    });
