<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\Payment;

class ClientPaymentController extends Controller
{
    public function invoice(Payment $payment)
    {
        $payment->load('paymentItems');
        $offer = CommercialOffer::query()
            ->with('organization:id,name,order_number')
            ->where('payment_id', $payment->id)
            ->first();

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'name' => $payment->name,
                'phone' => $payment->phone,
                'email' => $payment->email,
                'sum' => $payment->sum,
                'payment_type' => $payment->payment_type,
                'created_at' => $payment->created_at,
                'organization' => $offer?->organization
                    ? [
                        'id' => $offer->organization->id,
                        'name' => $offer->organization->name,
                        'order_number' => $offer->organization->order_number,
                    ]
                    : null,
            ],
            'items' => $payment->paymentItems->map(fn ($item) => [
                'service_name' => $item->service_name,
                'price' => $item->price,
            ])->values(),
        ]);
    }
}
