<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class OnlineCheckoutLinkService
{
    public function createUrl(Payment $payment): string
    {
        $type = strtolower(trim((string) $payment->payment_type));

        if ($type === 'alif') {
            return $this->generateAlifPayLink($payment);
        }

        if ($type === 'octo') {
            return $this->generateOctobankPayLink($payment);
        }

        throw new \InvalidArgumentException('Онлайн-оплата доступна только для Alif и Visa.');
    }

    private function generateOctobankPayLink(Payment $payment): string
    {
        $items = $payment->paymentItems()->get(['service_name', 'price']);

        if ($items->isEmpty()) {
            throw new \Exception('OCTO: пустая корзина (basket). Добавь хотя бы 1 позицию.');
        }

        $basketRows = $items->map(function ($i, $idx) {
            $priceCents = $this->moneyToCents($i->price);
            if ($priceCents <= 0) {
                throw new \Exception("OCTO: некорректная цена у позиции #{$idx}: {$i->price}");
            }

            return [
                'position_desc' => (string) $i->service_name,
                'count' => 1,
                'price' => $this->formatCents($priceCents),
                'spic' => '00305001001000000',
                '__price_cents' => $priceCents,
            ];
        })->values();

        $totalSumCents = $basketRows->reduce(function ($sum, $row) {
            $count = (int) ($row['count'] ?? 1);

            return $sum + ((int) ($row['__price_cents'] ?? 0) * $count);
        }, 0);

        $basket = $basketRows->map(function ($row) {
            unset($row['__price_cents']);

            return $row;
        })->all();

        if ($totalSumCents <= 0) {
            throw new \Exception('OCTO: итоговая сумма должна быть больше 0.');
        }

        $totalSum = $this->formatCents($totalSumCents);

        $payload = [
            'octo_shop_id' => (int) config('payments.octobank.shop_id'),
            'octo_secret' => (string) config('payments.octobank.shop_secret'),
            'shop_transaction_id' => (string) $payment->id,
            'auto_capture' => true,
            'test' => false,
            'init_time' => now()->format('Y-m-d H:i:s'),
            'user_data' => [
                'user_id' => (string) $payment->id,
                'phone' => (string) $payment->phone,
                'email' => (string) $payment->email,
            ],
            'total_sum' => $totalSum,
            'currency' => 'USD',
            'description' => 'Оплата услуг',
            'basket' => $basket,
            'return_url' => $this->paymentReturnUrl($payment),
            'notify_url' => route('client-payment.webhook.octo'),
            'language' => 'ru',
            'ttl' => 15,
        ];

        $resp = Http::asJson()
            ->acceptJson()
            ->post('https://secure.octo.uz/prepare_payment', $payload);

        $json = $resp->json();

        if (($json['error'] ?? null) === 0) {
            $paymentUpdate = ['sum' => $totalSum];
            if (Schema::hasColumn('payments', 'transaction_id')) {
                $paymentUpdate['transaction_id'] = $json['data']['octo_payment_UUID'] ?? null;
            }
            $payment->update($paymentUpdate);

            $payUrl = trim($this->toSafeString($json['data']['octo_pay_url'] ?? ''));
            if ($payUrl === '') {
                throw new \Exception('OCTO: ссылка оплаты не пришла в ответе провайдера.');
            }

            return $payUrl;
        }

        throw new \Exception('OCTO error: ' . ($json['errMessage'] ?? $resp->body()));
    }

    private function generateAlifPayLink(Payment $payment): string
    {
        $secretKey = trim($this->toSafeString(config('payments.alif.token')));
        $url = trim($this->toSafeString(config('payments.alif.url')));
        $paymentPage = trim($this->toSafeString(config('payments.alif.payment_page')));

        if (!$secretKey || !$url) {
            throw new \Exception('Alif Pay: не настроен token/url в config(payments.alif).');
        }
        if ($paymentPage === '') {
            throw new \Exception('Alif Pay: не настроен payment_page в config(payments.alif).');
        }

        $items = $payment->paymentItems()->get(['service_name', 'price']);
        if ($items->isEmpty()) {
            throw new \Exception('Alif Pay: пустой список услуг (items).');
        }

        $alifItems = $items->map(function ($item) {
            return [
                'name' => (string) $item->service_name,
                'amount' => 1,
                'price' => (int) round(((float) $item->price) * 100),
            ];
        })->values()->all();

        $amount = array_reduce($alifItems, static function ($sum, $i) {
            return $sum + ($i['price'] * $i['amount']);
        }, 0);

        $orderData = [
            'amount' => $amount,
            'order_id' => (string) $payment->id,
            'description' => 'Оплата услуг',
            'detail' => 'Оплата за услуги',
            'items' => $alifItems,
            'webhook_url' => route('client-payment.webhook.alif'),
            'email' => (string) $payment->email,
            'phone' => (string) $payment->phone,
            'full_name' => (string) $payment->name,
            'success_url' => $this->paymentReturnUrl($payment),
            'redirect_url' => $this->paymentReturnUrl($payment),
            'fail_url' => $this->paymentReturnUrl($payment),
        ];

        $response = Http::withHeaders([
            'Token' => $secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($url, $orderData);

        if (!$response->successful()) {
            throw new \Exception('Ошибка при создании платежа Alif Pay (HTTP ' . $response->status() . '): ' . $response->body());
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new \Exception('Alif Pay: ответ не JSON. Ответ: ' . $response->body());
        }

        $invoiceId = '';
        foreach ([
            data_get($json, 'id'),
            data_get($json, 'data.id'),
            data_get($json, 'invoice.id'),
            data_get($json, 'invoice_id'),
            data_get($json, 'result.id'),
            data_get($json, 'data.invoice_id'),
            data_get($json, 'data.invoice.id'),
            data_get($json, 'result.invoice_id'),
            data_get($json, 'result.invoice.id'),
            data_get($json, 'data'),
            data_get($json, 'invoice'),
            data_get($json, 'result'),
        ] as $candidate) {
            $value = $this->extractInvoiceId($candidate);
            if ($value !== '') {
                $invoiceId = $value;
                break;
            }
        }

        if ($invoiceId === '') {
            $providerMessage = trim($this->toSafeString(data_get($json, 'message') ?? data_get($json, 'error') ?? ''));
            $details = $providerMessage !== '' ? $providerMessage : $response->body();
            throw new \Exception('Alif Pay: не пришел invoice id. Ответ: ' . $details);
        }

        if (Schema::hasColumn('payments', 'transaction_id')) {
            $payment->forceFill([
                'transaction_id' => $invoiceId,
            ])->save();
        }

        return $paymentPage . rawurlencode($invoiceId);
    }

    private function paymentReturnUrl(Payment $payment): string
    {
        return route('client-payment.online.success', ['payment' => $payment->id]);
    }

    private function moneyToCents($value): int
    {
        $normalized = preg_replace('/\s+/', '', str_replace(',', '.', (string) $value));
        if ($normalized === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            throw new \Exception("OCTO: некорректный формат суммы: {$value}");
        }

        $isNegative = str_starts_with($normalized, '-');
        if ($isNegative) {
            $normalized = substr($normalized, 1);
        }

        [$wholePart, $fractionPart] = array_pad(explode('.', $normalized, 2), 2, '');
        $wholePart = ltrim($wholePart, '0');
        $whole = $wholePart === '' ? 0 : (int) $wholePart;

        $fraction = preg_replace('/\D+/', '', $fractionPart);
        $fraction = str_pad($fraction, 3, '0');
        $cents = (int) substr($fraction, 0, 2);
        $thirdDigit = (int) ($fraction[2] ?? '0');

        if ($thirdDigit >= 5) {
            $cents += 1;
            if ($cents >= 100) {
                $whole += 1;
                $cents = 0;
            }
        }

        $amount = ($whole * 100) + $cents;

        return $isNegative ? -$amount : $amount;
    }

    private function formatCents(int $amountCents): string
    {
        $abs = abs($amountCents);
        $formatted = number_format($abs / 100, 2, '.', '');

        return $amountCents < 0 ? "-{$formatted}" : $formatted;
    }

    private function toSafeString($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);

            return is_string($json) ? $json : '';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    private function extractInvoiceId($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['id', 'invoice_id', 'invoiceId', 'uuid', 'payment_id'] as $key) {
            $candidate = $value[$key] ?? null;
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        foreach ($value as $nested) {
            $candidate = $this->extractInvoiceId($nested);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
