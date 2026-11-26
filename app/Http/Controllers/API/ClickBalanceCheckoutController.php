<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Payment\ClickAdvancedShopService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ClickBalanceCheckoutController extends Controller
{
    public function __invoke(string $orderNumber, ClickAdvancedShopService $service): JsonResponse
    {
        try {
            $details = $service->checkoutDetails($orderNumber);
        } catch (Throwable) {
            return response()->json(['message' => 'Ссылка CLICK временно недоступна.'], 503);
        }

        return match ($details['error'] ?? null) {
            'organization' => response()->json(['message' => 'Организация не найдена.'], 404),
            'configuration' => response()->json(['message' => 'Ссылка CLICK не настроена.'], 503),
            default => response()->json($details),
        };
    }
}
