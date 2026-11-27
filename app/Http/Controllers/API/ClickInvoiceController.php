<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Payment\ClickAdvancedShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClickInvoiceController extends Controller
{
    public function __invoke(Request $request, ClickAdvancedShopService $service): JsonResponse
    {
        $data = $request->validate(['order_number' => ['required', 'string', 'max:255']]);

        $result = $service->createInvoice($data['order_number']);

        return match ($result['error'] ?? null) {
            'organization' => response()->json(['message' => 'Организация не найдена.'], 404),
            'phone' => response()->json(['message' => 'У организации нет корректного номера телефона Click.'], 422),
            'configuration' => response()->json(['message' => 'Click Merchant API не настроен.'], 503),
            'provider' => response()->json(['message' => 'Click не создал счёт.'], 502),
            default => response()->json($result, 201),
        };
    }
}
