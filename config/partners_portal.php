<?php

/*
 * Партнёрский портал partners.shamcrm.com.
 * PARTNERS_PORTAL_TOKEN — общий секрет, тот же в .env портала (BILLING_PORTAL_TOKEN).
 */
return [
    'token' => env('PARTNERS_PORTAL_TOKEN'),

    // Ставки уровня «Серебро», которые получает партнёр при одобрении заявки.
    'start_percent_tariff' => (int) env('PARTNERS_START_PERCENT_TARIFF', 40),
    'start_percent_pack' => (int) env('PARTNERS_START_PERCENT_PACK', 30),
];
