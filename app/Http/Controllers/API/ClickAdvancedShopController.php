<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Payment\ClickAdvancedShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

class ClickAdvancedShopController extends Controller
{
    public function __construct(private ClickAdvancedShopService $service)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_if((string) config('services.click_advanced.service_id') === '', 503);

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = null;
        }

        if (!is_array($payload) || array_is_list($payload)) {
            return response()->json(['error' => -8, 'error_note' => 'Некорректный JSON']);
        }

        return response()->json($this->service->handle($payload));
    }
}
