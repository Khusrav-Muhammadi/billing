<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyClickCallbackToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('services.sham.crm_token', ''));
        if ($expected === '') {
            return response()->json(['message' => 'BILLING_CRM_TOKEN не настроен.'], 503);
        }

        $authorization = trim((string) $request->header('Authorization', ''));
        $provided = strncasecmp($authorization, 'Bearer ', 7) === 0
            ? trim(substr($authorization, 7))
            : '';

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(
                ['message' => 'Неверный или отсутствующий bearer token.'],
                401,
                ['WWW-Authenticate' => 'Bearer'],
            );
        }

        return $next($request);
    }
}
