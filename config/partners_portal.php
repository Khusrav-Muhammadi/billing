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

    // Тёплые лиды: заявки на демо с сайта раздаются партнёрам. Включать после деплоя CRM (api/partner-leads).
    'leads' => [
        'enabled' => (bool) env('PARTNERS_LEADS_ENABLED', false),
        'sla_hours' => (int) env('PARTNERS_LEADS_SLA_HOURS', 24),
        // Своя компания (SOFTTECH GROUP) лиды не получает.
        'excluded_partner_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env('PARTNERS_LEADS_EXCLUDED_IDS', '11'))))),
    ],
];
