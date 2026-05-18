<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    public function invoice(Payment $payment)
    {
        $payment->load('paymentItems');
        $offer = CommercialOffer::query()
            ->with('organization:id,name,legal_name,INN,email,phone,order_number')
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
                        'legal_name' => $offer->organization->legal_name,
                        'INN' => $offer->organization->INN,
                        'email' => $offer->organization->email,
                        'phone' => $offer->organization->phone,
                        'order_number' => $offer->organization->order_number,
                    ]
                    : null,
            ],
            'items' => $payment->paymentItems->map(fn ($item) => [
                'service_name' => $item->service_name,
                'price' => $item->price,
            ])->values(),
            'offer' => $offer
        ]);
    }

    public function updateInvoiceCustomer(Payment $payment, Request $request)
    {
        $data = $request->validate([
            'field' => ['required', 'string', 'in:legal_name,INN,email,phone'],
            'value' => ['nullable', 'string', 'max:255'],
        ]);

        $offer = CommercialOffer::query()
            ->with('organization:id,name,legal_name,INN,email,phone,order_number')
            ->where('payment_id', $payment->id)
            ->firstOrFail();

        $organization = $offer->organization;
        abort_if(!$organization, 404, 'Организация не найдена');

        $field = $data['field'];
        $value = trim((string) ($data['value'] ?? ''));

        $organization->forceFill([
            $field => $value !== '' ? $value : null,
        ])->save();

        $organization->refresh();

        return response()->json([
            'success' => true,
            'customer' => $this->invoiceCustomerData($organization),
        ]);
    }

    private function invoiceCustomerData(Organization $organization): array
    {
        return [
            'legal_name' => (string) ($organization->legal_name ?: $organization->name ?: ''),
            'INN' => (string) ($organization->INN ?? ''),
            'email' => (string) ($organization->email ?? ''),
            'phone' => (string) ($organization->phone ?? ''),
        ];
    }
}
