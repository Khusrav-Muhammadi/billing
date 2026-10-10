<?php

namespace App\Http\Middleware;

use App\Support\CrmHttp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пускает только запросы от CRM с правильным X-Service-Token.
 * Используется на server-to-server роутах (change-sub-domain, clients-balance, legal-info ...).
 * Алиас: crm.token.
 */
class VerifyCrmServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('services.sham.crm_token', ''));
        if ($expected === '') {
            return response()->json(['message' => 'BILLING_CRM_TOKEN не настроен.'], 503);
        }

        $provided = trim((string) $request->header(CrmHttp::HEADER, ''));

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Неверный или отсутствующий сервисный токен.'], 401);
        }

        return $next($request);
    }
}
