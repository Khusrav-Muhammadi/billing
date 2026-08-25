<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_SECRET_ID'),
        'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_FOLDER_ID'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'mail_from' => env('BREVO_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'mail_from_name' => env('BREVO_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'shamCRM')),
    ],

    'sham' => [
        'domain' => env('APP_DOMAIN'),
    ],

];
