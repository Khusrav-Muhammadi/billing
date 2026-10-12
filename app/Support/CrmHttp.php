<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP-клиент для вызовов биллинг → CRM ({sub}-new-back.{domain}/api/...).
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

    /**
     * Базовый URL тенанта CRM: https://{sub}-new-back.{domain}{path}
     *
     * sham → sham-new-back. Если поддомен уже кончается на -new
     * (старые клиенты), второй -new не добавляем.
     */
    public static function tenantUrl(string $subDomain, string $domain, string $path = ''): string
    {
        $sub = trim($subDomain);

        if ($sub !== '' && !str_ends_with(strtolower($sub), '-new')) {
            $sub .= '-new';
        }

        $url = 'https://' . $sub . '-back.' . $domain;

        if ($path === '') {
            return $url;
        }

        return $url . '/' . ltrim($path, '/');
    }

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
