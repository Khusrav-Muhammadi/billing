<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InvoiceRequest;
use App\Models\Client;
use App\Models\Tariff;
use Illuminate\Support\Facades\Http;


class PaymentController1 extends Controller
{

    public function createInvoice(InvoiceRequest $request)
    {
        $data = $request->validated();
        $token = 'vaCx7qkNNahFb9LhN+ZF2EqhT/+9c8uK5LrGXvGoG/yf';
        $url = 'https://api-dev.alifpay.uz/v2/invoice';

        $subdomain = parse_url($request->fullUrl(), PHP_URL_HOST);

        $tariff = Tariff::where('name', $data['tariff_name'])->first();
        $client = Client::where('sub_domain', $subdomain)->first();

        $response = Http::withHeaders([
            'Token' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',

        ])->post($url, [
            'items' => [
                [
                    'name' => 'Лицензия для тарифа ' . $tariff->name,
                    'amount' => 1,
                    'spic' => '08517001001000000', // настоящий SPIC
                    'price' => $tariff->price,
                    'marking_code' => '111111', // если требуется
                    'vat_percent' => 12,
                    'discount' => 0
                ],
                [
                    'name' => 'Ежемесячная оплата тарифа ' . $tariff->name,
                    'spic' => '10399002001000000',
                    'amount' => 1,
                    'price' => $tariff->price,
                    'vat_percent' => 12,
                    'discount' => 0
                ]
            ],
            'receipt' => true,
            'phone' => $client->phone,
            'cancel_url' => 'https://yourdomain.uz/payment/cancel',
            'redirect_url' => 'https://yourdomain.uz/payment/complete',
            'webhook_url' => 'https://yourdomain.uz/api/alifpay/webhook',
            'timeout' => 86400,
            'meta' => [
                'client_id' => $client->id,
                'tariff_id' => $tariff->id
            ]
        ]);

        $data = $response->json();


        return 'https://checkout-dev.alifpay.uz?invoice=' . $data['id'];

    }

}
