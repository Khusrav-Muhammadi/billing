<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymeMerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        if (!is_array($payload) || array_is_list($payload)
            || $id === null || !is_string($payload['method'] ?? null)
            || !is_array($payload['params'] ?? null)) {
            return response()->json($this->service->error(
                -32600, 'Некорректный запрос', 'Invalid request', "Noto'g'ri so'rov"
            ) + ['id' => $id]);
        }

        $result = $this->service->handle($payload['method'], $payload['params']) + ['id' => $id];
        Log::info('Payme merchant callback', [
            'method' => $payload['method'],
            'rpc_id' => $id,
            'params' => $payload['params'],
            'response' => $result,
        ]);

        return response()->json($result);
    }
}
