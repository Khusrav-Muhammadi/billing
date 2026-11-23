<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server роуты партнёрского портала (partners.shamcrm.com).
 * Общий секрет PARTNERS_PORTAL_TOKEN в .env обоих проектов, заголовок X-Partners-Token.
 * Алиас: partners.token.
 */
class VerifyPartnersPortalToken
{
    public const HEADER = 'X-Partners-Token';

    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('partners_portal.token', ''));
        if ($expected === '') {
            return response()->json(['message' => 'PARTNERS_PORTAL_TOKEN не настроен.'], 503);
        }

        $provided = trim((string) $request->header(self::HEADER, ''));

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Неверный или отсутствующий токен портала.'], 401);
        }

        return $next($request);
    }
}
