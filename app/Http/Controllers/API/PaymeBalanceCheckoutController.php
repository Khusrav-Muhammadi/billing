<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymeMerchantService;
use Illuminate\Http\JsonResponse;
use Throwable;

class PaymeBalanceCheckoutController extends Controller
{
    public function __invoke(string $orderNumber, PaymeMerchantService $service): JsonResponse
    {
        try {
            $url = $service->checkoutUrl($orderNumber);
        } catch (Throwable) {
            return response()->json(['message' => 'Ссылка Payme временно недоступна.'], 503);
        }

        if ($url === null) {
            return response()->json(['message' => 'Организация не найдена.'], 404);
        }

        return response()->json([
            'checkout_url' => $url,
            'amount' => PaymeMerchantService::TOP_UP_UZS,
            'amount_tiyin' => PaymeMerchantService::TOP_UP_TIYIN,
        ]);
    }
}
