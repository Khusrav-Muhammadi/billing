<?php

return [
    // Токен сайта shamcrm.com (заголовок X-Site-Token). Только из .env.
    'api_token' => env('SITE_API_TOKEN'),
    'token_header' => env('SITE_TOKEN_HEADER', 'X-Site-Token'),
];
