<?php

return [
    // Токен 1С для api/client-activation (заголовок X-Internal-Token). Только из .env; пустой — роут закрыт.
    'internal_api_token' => env('INTERNAL_API_TOKEN'),
];
