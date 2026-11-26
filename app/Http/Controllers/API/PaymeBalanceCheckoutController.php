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
            $details = $service->checkoutDetails($orderNumber);
        } catch (Throwable) {
            return response()->json(['message' => 'Ссылка Payme временно недоступна.'], 503);
        }

        if (($details['error'] ?? null) === 'organization') {
            return response()->json(['message' => 'Организация не найдена.'], 404);
        }

        if (($details['error'] ?? null) === 'offer') {
            return response()->json(['message' => 'Нет неоплаченного подключения в сумах.'], 404);
        }

        return response()->json([
            'checkout_url' => $details['checkout_url'],
            'amount' => $details['amount'],
            'amount_tiyin' => $details['amount_tiyin'],
            'commercial_offer_id' => $details['commercial_offer_id'],
        ]);
    }
}
