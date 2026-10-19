<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyClickCallbackToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) config('services.click_advanced.service_id', '');
        $password = trim((string) config('services.sham.crm_token', ''));
        if ($username === '' || $password === '') {
            return response()->json(['message' => 'CLICK Basic Auth не настроен.'], 503);
        }

        $authorization = trim((string) $request->header('Authorization', ''));
        $decoded = strncasecmp($authorization, 'Basic ', 6) === 0
            ? base64_decode(substr($authorization, 6), true)
            : false;
        [$providedUsername, $providedPassword] = is_string($decoded) && str_contains($decoded, ':')
            ? explode(':', $decoded, 2)
            : ['', ''];

        if (!hash_equals($username, $providedUsername) || !hash_equals($password, $providedPassword)) {
            return response()->json(
                ['message' => 'Неверные или отсутствующие данные Basic Auth.'],
                401,
                ['WWW-Authenticate' => 'Basic realm="CLICK Advanced Shop"'],
            );
        }

        return $next($request);
    }
}
