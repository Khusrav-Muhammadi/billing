<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\ClientPaymentInvoiceEmailService;
use App\Services\Payment\InvoicePaymentItemPresenter;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    public function invoice(Payment $payment, InvoicePaymentItemPresenter $presenter)
    {
        $payment->load('paymentItems');
        $offer = CommercialOffer::query()
            ->with([
                'organization:id,name,legal_name,INN,email,phone,order_number',
                'items:id,commercial_offer_id,tariff_id,quantity,unit_price,months,total_price',
                'items.tariff:id,name',
                'aiItems.plan:id,name,category',
            ])
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
            'items' => $presenter->toApiItems($payment, $offer),
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

    public function sendInvoiceEmail(Payment $payment, ClientPaymentInvoiceEmailService $invoiceEmailService)
    {
        try {
            $result = $invoiceEmailService->send($payment);

            return response()->json([
                'success' => true,
                'message' => 'Счет отправлен на почту: ' . $result['email'],
                'email' => $result['email'],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
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
