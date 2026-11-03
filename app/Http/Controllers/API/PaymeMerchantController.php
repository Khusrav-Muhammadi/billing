<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymeMerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

class PaymeMerchantController extends Controller
{
    public function __construct(private PaymeMerchantService $service)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json($this->service->error(
                -32700, 'Ошибка разбора JSON', 'JSON parse error', 'JSON tahlil xatosi'
            ) + ['id' => null]);
        }

        $id = is_array($payload) && is_int($payload['id'] ?? null) ? $payload['id'] : null;
        if (!$this->authorized($request)) {
            return response()->json($this->service->error(
                -32504, 'Недостаточно привилегий', 'Insufficient privileges', 'Ruxsat yetarli emas'
            ) + ['id' => $id]);
        }

        if (!is_array($payload) || array_is_list($payload)
            || $id === null || !is_string($payload['method'] ?? null)
            || !is_array($payload['params'] ?? null)) {
            return response()->json($this->service->error(
                -32600, 'Некорректный запрос', 'Invalid request', "Noto'g'ri so'rov"
            ) + ['id' => $id]);
        }

        return response()->json($this->service->handle($payload['method'], $payload['params']) + ['id' => $id]);
    }

    private function authorized(Request $request): bool
    {
        $login = (string) config('services.payme_merchant.login');
        $key = (string) config('services.payme_merchant.key');
        if ($login === '' || $key === '') {
            return false;
        }

        $header = (string) $request->header('Authorization', '');
        $decoded = strncasecmp($header, 'Basic ', 6) === 0
            ? base64_decode(substr($header, 6), true)
            : false;
        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            return false;
        }

        [$providedLogin, $providedKey] = explode(':', $decoded, 2);

        return hash_equals($login, $providedLogin) && hash_equals($key, $providedKey);
    }
}
