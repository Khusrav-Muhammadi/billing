<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySiteApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('site.api_token', ''));
        dd($expected);
        if ($expected === '') {
            return response()->json([
                'message' => 'SITE_API_TOKEN не настроен.',
            ], 503);
        }

        $provided = trim((string) $request->header((string) config('site.token_header', 'X-Site-Token'), ''));
        if ($provided === '') {
            $authorization = trim((string) $request->header('Authorization', ''));
            if (str_starts_with(strtolower($authorization), 'bearer ')) {
                $provided = trim(substr($authorization, 7));
            }
        }

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Неверный или отсутствующий токен.',
            ], 401);
        }

        return $next($request);
    }
}
