<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Выдача демо-доступа с сайта
    |--------------------------------------------------------------------------
    */

    /*
     * Сколько живёт одноразовая ссылка входа, которую выдаёт CRM.
     * Должно совпадать с demo.login_token_ttl_minutes на стороне CRM.
     *
     * Здесь и ниже `?:` вместо второго аргумента env(): пустая переменная в
     * .env превратилась бы в 0, а нулевой TTL и нулевой таймаут ломают выдачу
     * тихо и неочевидно.
     */
    'login_token_ttl_minutes' => (int) (env('DEMO_LOGIN_TOKEN_TTL') ?: 15),

    // Тариф демо-организации по стране клиента (country_id).
    'tariff' => [
        'by_country' => [
            2 => 8, // Узбекистан
        ],
        'default' => 4,
    ],

    /*
     * Адреса CRM. Через `?:`, а не через второй аргумент env(): объявленная в
     * .env, но пустая переменная даёт '', и дефолт бы не подставился — демо
     * молча падало бы с «cURL error 3: missing URL».
     */
    'endpoints' => [
        'create_subdomain' => env('DEMO_CREATE_SUBDOMAIN_URL') ?: 'https://shamcrm.com/api/createSubdomain',
        'delete_subdomain' => env('DEMO_DELETE_SUBDOMAIN_URL') ?: 'https://shamcrm.com/api/deleteSubdomain',
    ],

    'crm_check_email_url' => env('DEMO_CRM_CHECK_EMAIL_URL') ?: 'https://shamcrm.com/api/check-email',

    'provisioning' => [
        'subdomain_timeout' => (int) (env('DEMO_SUBDOMAIN_TIMEOUT') ?: 90),
        'crm_timeout' => (int) (env('DEMO_CRM_TIMEOUT') ?: 15),
        'crm_attempts' => (int) (env('DEMO_CRM_ATTEMPTS') ?: 6),
        'crm_retry_delay_ms' => (int) (env('DEMO_CRM_RETRY_DELAY_MS') ?: 1500),

        // Заявка, зависшая дольше этого срока, считается провалившейся:
        // воркер мог умереть, не успев записать причину.
        'stale_after_minutes' => (int) (env('DEMO_STALE_AFTER_MINUTES') ?: 15),
    ],

    'turnstile' => [
        'secret_key' => env('TURNSTILE_SECRET_KEY'),

        // Пока false, токен проверяется только если он пришёл. Включать после
        // того, как виджет на сайте гарантированно отдаёт токен.
        'required' => (bool) env('TURNSTILE_REQUIRED', false),
    ],

    // На эти адреса не выдаём демо: доступы уходят письмом, а одноразовый
    // ящик через десять минут исчезнет вместе с ними.
    'disposable_email_domains' => [
        '10minutemail.com',
        'temp-mail.org',
        'tempmail.org',
        'guerrillamail.com',
        'mailinator.com',
        'yopmail.com',
        'throwawaymail.com',
        'getnada.com',
        'trashmail.com',
        'dropmail.me',
        'sharklasers.com',
        'maildrop.cc',
        'fakeinbox.com',
        'mytemp.email',
        'temp-mail.io',
        'mohmal.com',
    ],

];
