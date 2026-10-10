<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP-клиент для вызовов биллинг → CRM ({sub_domain}-back.{domain}/api/...).
 *
 * Все такие вызовы идут с общим секретом в заголовке X-Service-Token.
 * CRM проверяет его middleware'ом billing.token. Тот же секрет CRM
 * присылает при вызовах в биллинг (middleware crm.token здесь).
 *
 * Если токен не настроен — бросаем исключение, а не шлём запрос без подписи:
 * лучше сломаться громко, чем тихо держать интеграцию открытой.
 */
class CrmHttp
{
    public const HEADER = 'X-Service-Token';

    public static function client(): PendingRequest
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            self::HEADER => self::token(),
        ]);
    }

    public static function token(): string
    {
        $token = trim((string) config('services.sham.crm_token', ''));

        if ($token === '') {
            throw new RuntimeException('BILLING_CRM_TOKEN is not configured (services.sham.crm_token).');
        }

        return $token;
    }
}
