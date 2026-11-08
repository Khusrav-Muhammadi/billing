<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPaymeAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->header('Authorization', '');

        $decoded = preg_match('/^Basic\s+(\S+)$/i', $authorization, $matches)
            ? base64_decode($matches[1], true)
            : false;

        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            return $this->unauthorizedResponse($request);
        }

        [$username, $password] = explode(':', $decoded, 2);

        $login = (string) config('services.payme_merchant.login');

        $key = (string) config('services.payme_merchant.key');

        $testLogin = (string) config('services.payme_merchant.test_login');

        $testKey = (string) config('services.payme_merchant.test_key');

        if ($testLogin !== '' && $testKey !== ''
            && hash_equals($testLogin, $username) && hash_equals($testKey, $password)) {
            return $next($request);
        }

        if ($login !== '' && $key !== ''
            && hash_equals($login, $username) && hash_equals($key, $password)) {
            return $next($request);
        }

        return $this->unauthorizedResponse($request);
    }

    private function unauthorizedResponse(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_BIGINT_AS_STRING);
        $id = is_array($payload) && is_int($payload['id'] ?? null) ? $payload['id'] : null;

        return response()->json([
            'error' => [
                'code' => -32504,
                'message' => [
                    'uz' => "Avtorizatsiyadan o'tishda xatolik",
                    'ru' => 'Ошибка аутентификации',
                    'en' => 'Auth error',
                ],
            ],
            'id' => $id,
        ]);
    }
}
