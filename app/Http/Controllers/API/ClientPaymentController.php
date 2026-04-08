<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class ClientPaymentController extends Controller
{
    public function invoice(Payment $payment)
    {
        $payment->load('paymentItems');

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'name' => $payment->name,
                'phone' => $payment->phone,
                'email' => $payment->email,
                'sum' => $payment->sum,
                'payment_type' => $payment->payment_type,
                'created_at' => $payment->created_at,
            ],
            'items' => $payment->paymentItems->map(fn ($item) => [
                'service_name' => $item->service_name,
                'price' => $item->price,
            ])->values(),
        ]);
    }
}

