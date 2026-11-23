<?php

/*
 * Ключи провайдеров оплаты — только в .env. Раньше лежали в этом файле, то есть в git:
 * после переноса их нужно сменить в кабинетах Alif и Octo и обновить .env.
 */
return [
    'alif' => [
        'token' => env('ALIF_TOKEN'),
        'token_test' => env('ALIF_TOKEN_TEST'),
        'url_test' => env('ALIF_URL_TEST', 'https://api-dev.alifpay.uz/v2/invoice'),
        'url' => env('ALIF_URL', 'https://api.alifpay.uz/v2/invoice'),
        'payment_page' => env('ALIF_PAYMENT_PAGE', 'https://checkout.alifpay.uz?invoice='),
        'payment_page_dev' => env('ALIF_PAYMENT_PAGE_DEV', 'https://checkout-dev.alifpay.uz?invoice='),
    ],

    'octobank' => [
        'shop_id' => env('OCTO_SHOP_ID'),
        'shop_secret' => env('OCTO_SHOP_SECRET'),
        'url' => env('OCTO_URL', 'https://secure.octo.uz/prepare_payment'),
    ],
];
