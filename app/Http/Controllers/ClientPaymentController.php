<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientPaymentRequest;
use App\Models\Payment;
use App\Models\PaymentItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClientPaymentController extends Controller
{

    public function index()
    {
        $clients = Payment::query()
            ->with('paymentItems')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('payments', compact('clients'));
    }

    public function store(ClientPaymentRequest $request)
    {
        $data = $request->validated();

        $payment = Payment::create([
            'name' => $data['name'],
            'phone' => preg_replace('/\D+/', '', $data['phone']),
            'email' => $data['email'],
            'sum' => $data['sum'],
            'payment_type' => $data['payment_type']
        ]);

        foreach ($data['data'] as $datum) {
            PaymentItem::create([
                'payment_id' => $payment->id,
                'service_name' => $datum['name'],
                'price' => $datum['price'],
            ]);
        }

        if ($data['payment_type'] == 'alif') {
            $checkoutUrl = $this->generateAlifPayLink($payment);
            return redirect($checkoutUrl);
        }

        if ($data['payment_type'] == 'octo') {
            $checkoutUrl = $this->generateOctobankPayLink($payment);
            return redirect($checkoutUrl);
        }

        return redirect()->back();
    }

    private function generateOctobankPayLink(Payment $payment)
    {
        // Берём позиции из БД через relation
        $items = $payment->paymentItems()->get(['service_name', 'price']);

        if ($items->isEmpty()) {
            throw new \Exception('OCTO: пустая корзина (basket). Добавь хотя бы 1 позицию.');
        }

        // Собираем basket
        $basket = $items->map(function ($i, $idx) {
            // если price может прийти строкой "1,23" — нормализуем
            $raw = str_replace(',', '.', (string) $i->price);
            $price = (float) $raw;

            // Важно: цена должна быть > 0
            if ($price <= 0) {
                throw new \Exception("OCTO: некорректная цена у позиции #{$idx}: {$i->price}");
            }

            return [
                "position_desc" => (string) $i->service_name,
                "count" => 1,
                "price" => $price,
                "spic" => "00305001001000000", // поставь свой корректный SPIC
            ];
        })->values()->all();

        // total_sum считаем из basket (чтобы не было ошибки как у тебя)
        $totalSum = array_reduce($basket, fn ($s, $b) => $s + ($b['price'] * $b['count']), 0);

        $payload = [
            "octo_shop_id" => (int) config('payments.octobank.shop_id'),
            "octo_secret" => (string) config('payments.octobank.shop_secret'),
            "shop_transaction_id" => (string) $payment->id,
            "auto_capture" => true,
            "test" => false,
            "init_time" => now()->format('Y-m-d H:i:s'),
            "user_data" => [
                "user_id" => (string) $payment->id,
                "phone" => (string) $payment->phone,
                "email" => (string) $payment->email,
            ],
            "total_sum" => $totalSum,
            "currency" => "UZS",
            "description" => "Оплата услуг",
            "basket" => $basket,
            "return_url" => "https://shamcrm.com/success",
            "notify_url" => "https://shamcrm.com/notify",
            "language" => "ru",
            "ttl" => 15,
        ];

        $resp = Http::asJson()
            ->acceptJson()
            ->post("https://secure.octo.uz/prepare_payment", $payload);

        $json = $resp->json();

        if (($json['error'] ?? null) === 0) {
            $payment->update([
                'transaction_id' => $json['data']['octo_payment_UUID'] ?? null,
                'sum' => $totalSum, // опционально: зафиксировать сумму как рассчитанную
            ]);

            return $json['data']['octo_pay_url'] ?? null;
        }

        throw new \Exception("OCTO error: " . ($json['errMessage'] ?? $resp->body()));
    }

    private function generateOctobankSignature(array $params, int $amount): string
    {
        // Согласно документации: octo_shop_id;amount;octo_secret;shop_transaction_id;
        // auto_capture;test;init_time;user_id;tag_online

        $signatureString = implode(';', [
                $params['octo_shop_id'],
                $amount,
                $params['octo_secret'],
                $params['shop_transaction_id'],
                $params['auto_capture'],
                $params['test'],
                $params['init_time'],
                $params['user_id'],
                $params['tag_online'],
            ]) . ';';

        return hash('sha256', $signatureString);
    }

    private function generateAlifPayLink(Payment $payment)
    {
        $secretKey = config('payments.alif.token');
        $url = config('payments.alif.url');

        // Берём позиции из БД
        $items = $payment->paymentItems()->get(['service_name', 'price']);

        if ($items->isEmpty()) {
            throw new \Exception('Alif Pay: пустой список услуг (items).');
        }

        // ВНИМАНИЕ:
        // Если price хранится в основной валюте (например 150.50) -> умножаем на 100
        // Если price уже в копейках/тийинах -> убери *100
        $alifItems = $items->map(function ($item) {
            return [
                'name'  => (string) $item->service_name,
                'amount' => 1,
                'price' => (int) round(((float) $item->price) * 100),
            ];
        })->values()->all();

        // amount = сумма всех позиций (чтобы не было рассинхрона)
        $amount = array_reduce($alifItems, function ($sum, $i) {
            return $sum + ($i['price'] * $i['amount']);
        }, 0);

        $orderData = [
            'amount' => $amount,
            'order_id' => (string) $payment->id,
            'description' => 'Оплата услуг',
            'detail' => 'Оплата за услуги',
            'items' => $alifItems, // <-- КЛЮЧЕВО
            'email' => (string) $payment->email,
            'phone' => (string) $payment->phone,
            'full_name' => (string) $payment->name,
            'success_url' => 'https://rasulovfingrouptj.shamcrm.com/customers',
            'fail_url' => 'https://rasulovfingrouptj.shamcrm.com/customers',
        ];

        $response = Http::withHeaders([
            'Token' => $secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($url, $orderData);

        if ($response->successful()) {
            $result = $response->json();

            $payment->update([
                'transaction_id' => $result['transaction_id'] ?? $result['id'] ?? null
            ]);

            $checkoutUrl = config('payments.alif.payment_page') . $response['id'];

            if (!$checkoutUrl) {
                throw new \Exception('Alif Pay: не пришла ссылка на оплату (url/checkout_url). Ответ: ' . $response->body());
            }

            return $checkoutUrl;
        }

        throw new \Exception('Ошибка при создании платежа Alif Pay: ' . $response->body());
    }

}
