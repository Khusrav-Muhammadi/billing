<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientPaymentRequest;
use App\Models\CommercialOffer;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function invoice(Payment $payment)
    {
        $payment->load('paymentItems');
        $offer = CommercialOffer::query()
            ->with('organization:id,name,order_number')
            ->where('payment_id', $payment->id)
            ->first();

        return view('payments-invoice', [
            'payment' => $payment,
            'offer' => $offer,
            'organizationOrderNumber' => (string) ($offer?->organization?->order_number ?? ''),
        ]);
    }

    public function store(ClientPaymentRequest $request)
    {
        $data = $request->validated();
        $commercialOffer = null;
        $commercialOfferId = isset($data['commercial_offer_id']) ? (int) $data['commercial_offer_id'] : null;

        try {
            if ($commercialOfferId) {
                $commercialOffer = CommercialOffer::query()
                    ->with([
                        'latestOfferStatus' => function ($query) {
                            $query->select([
                                'commercial_offer_statuses.id',
                                'commercial_offer_statuses.commercial_offer_id',
                                'commercial_offer_statuses.status',
                            ]);
                        },
                    ])
                    ->find($commercialOfferId);
                if (!$commercialOffer) {
                    throw new \Exception('КП не найдено.');
                }

                $isPaidOffer = (string) ($commercialOffer->latestOfferStatus?->status ?? '') === 'paid'
                    || (string) ($commercialOffer->status ?? '') === 'paid';

                if ($isPaidOffer) {
                    throw new \Exception('Это подключение уже оплачено. Повторная генерация ссылки недоступна.');
                }
            }

            DB::beginTransaction();

            $organizationId = $data['organization_id'] ?? null;
            $partnerId = isset($data['partner_id']) ? (int) $data['partner_id'] : null;
            if (!$organizationId && isset($data['name']) && is_numeric($data['name'])) {
                $organizationId = (int) $data['name'];
            }

            $partner = null;
            if ($partnerId) {
                $partner = User::query()
                    ->where('id', $partnerId)
                    ->whereRaw('LOWER(role) = ?', ['partner'])
                    ->select('id', 'name', 'email', 'phone', 'account_id')
                    ->first();

                if (!$partner) {
                    throw new \Exception('Партнёр не найден.');
                }
            }

            if ($organizationId) {
                $organization = Organization::query()
                    ->with('client:id,name,phone,email,contact_person')
                    ->find($organizationId);

                if (!$organization) {
                    throw new \Exception('Организация не найдена');
                }

                if ($commercialOffer && (int) $commercialOffer->organization_id !== (int) $organization->id) {
                    throw new \Exception('Организация платежа не совпадает с сохраненным КП.');
                }

                if ($partner) {
                    $data['name'] = (string) ($partner->name ?? ($data['name'] ?? ''));
                    $data['phone'] = (string) ($partner->phone ?? ($data['phone'] ?? ''));
                    $data['email'] = (string) ($partner->email ?? ($data['email'] ?? ''));
                } else {
                    $data['name'] = (string) ($organization->name ?? $data['name']);
                    $data['phone'] = (string) ($organization->phone ?: ($organization->client?->phone ?: ($data['phone'] ?? '')));
                    $data['email'] = (string) ($organization->email ?: ($organization->client?->email ?: ($data['email'] ?? '')));
                }
            }

            if (!isset($data['email']) || trim((string) $data['email']) === '') {
                if ($partner) {
                    throw new \Exception('У выбранного партнёра не указан email для оплаты.');
                }
                throw new \Exception('Не указана почта (email) для оплаты. Укажи email у организации или клиента.');
            }

            if ($partner && (!isset($data['phone']) || trim((string) $data['phone']) === '')) {
                throw new \Exception('У выбранного партнёра не указан номер телефона для оплаты.');
            }

            $payment = Payment::create([
                'name' => trim($this->toSafeString($data['name'] ?? '')),
                'phone' => preg_replace('/\D+/', '', $this->toSafeString($data['phone'] ?? '')),
                'email' => trim($this->toSafeString($data['email'] ?? '')),
                'sum' => $this->toDecimal($data['sum'] ?? 0),
                'payment_type' => trim($this->toSafeString($data['payment_type'] ?? '')),
            ]);

            $statusAccountId = null;
            if (($data['payment_type'] ?? null) === 'invoice' && $partner && $partner->account_id) {
                $statusAccountId = (int) $partner->account_id;
            }

            $paymentItems = is_array($data['data'] ?? null) ? $data['data'] : [];
            if (empty($paymentItems)) {
                throw new \Exception('Список услуг для оплаты пуст.');
            }

            foreach ($paymentItems as $datum) {
                $serviceName = trim($this->toSafeString(data_get($datum, 'name')));
                $price = $this->toDecimal(data_get($datum, 'price'));

                if ($serviceName === '') {
                    throw new \Exception('Некорректное название услуги в данных оплаты.');
                }
                if ($price <= 0) {
                    throw new \Exception('Некорректная сумма позиции оплаты.');
                }

                PaymentItem::create([
                    'payment_id' => $payment->id,
                    'service_name' => $serviceName,
                    'price' => $price,
                ]);
            }

            DB::commit();

            if ($data['payment_type'] === 'invoice') {
                $url = route('client-payment.invoice', $payment);
                // When payment is created via API (partners app), open invoice page in partners UI.
                if ($request->is('api/*')) {
                    $partnersBaseUrl = rtrim((string) config('partners.url', 'https://partners.shamcrm.com'), '/');
                    if ($partnersBaseUrl !== '') {
                        $url = $partnersBaseUrl . '/client-payment/invoice/' . $payment->id;
                    }
                }
                $this->lockCommercialOfferAfterPayment($commercialOffer, $payment, $url, $statusAccountId);
                return $request->expectsJson()
                    ? response()->json(['redirect_url' => $url])
                    : redirect()->to($url);
            }

            if ($data['payment_type'] === 'cash') {
                $url = route('application.index');
                $this->lockCommercialOfferAfterPayment($commercialOffer, $payment, null, null);
                return $request->expectsJson()
                    ? response()->json(['redirect_url' => $url])
                    : redirect()->to($url);
            }

            if ($data['payment_type'] == 'alif') {
                $checkoutUrl = $this->generateAlifPayLink($payment);
                $this->lockCommercialOfferAfterPayment($commercialOffer, $payment, $checkoutUrl, null);
                return $request->expectsJson()
                    ? response()->json(['redirect_url' => $checkoutUrl])
                    : redirect($checkoutUrl);
            }

            if ($data['payment_type'] == 'octo') {
                $checkoutUrl = $this->generateOctobankPayLink($payment);
                $this->lockCommercialOfferAfterPayment($commercialOffer, $payment, $checkoutUrl, null);
                return $request->expectsJson()
                    ? response()->json(['redirect_url' => $checkoutUrl])
                    : redirect($checkoutUrl);
            }

            return $request->expectsJson()
                ? response()->json(['redirect_url' => url()->previous()])
                : redirect()->back();

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('ClientPaymentController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payment_type' => $data['payment_type'] ?? null,
                'sum' => $data['sum'] ?? null,
                'commercial_offer_id' => $commercialOfferId,
            ]);

            $msg = 'Ошибка при создании платежа: ' . $e->getMessage();
            return $request->expectsJson()
                ? response()->json(['error' => $msg], 422)
                : redirect()->back()->withErrors(['error' => $msg]);
        }
    }

    private function generateOctobankPayLink(Payment $payment)
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
                "position_desc" => (string) $i->service_name,
                "count" => 1,
                "price" => $this->formatCents($priceCents),
                "spic" => "00305001001000000",
                "__price_cents" => $priceCents,
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
            "currency" => "USD",
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
            try {
                DB::beginTransaction();

                $payment->update([
                    'transaction_id' => $json['data']['octo_payment_UUID'] ?? null,
                    'sum' => $totalSum,
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return $json['data']['octo_pay_url'] ?? null;
        }

        throw new \Exception("OCTO error: " . ($json['errMessage'] ?? $resp->body()));
    }

    private function generateAlifPayLink(Payment $payment)
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
                'name'  => (string) $item->service_name,
                'amount' => 1,
                'price' => (int) round(((float) $item->price) * 100),
            ];
        })->values()->all();

        $amount = array_reduce($alifItems, function ($sum, $i) {
            return $sum + ($i['price'] * $i['amount']);
        }, 0);

        $orderData = [
            'amount' => $amount,
            'order_id' => (string) $payment->id,
            'description' => 'Оплата услуг',
            'detail' => 'Оплата за услуги',
            'items' => $alifItems,
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

        return $paymentPage . rawurlencode($invoiceId);
    }

    private function lockCommercialOfferAfterPayment(?CommercialOffer $offer, Payment $payment, ?string $paymentLink = null, ?int $statusAccountId = null): void
    {
        if (!$offer) {
            return;
        }

        $paymentMethod = match (strtolower((string) $payment->payment_type)) {
            'invoice' => 'invoice',
            'cash' => 'cash',
            default => 'card',
        };

        $updated = CommercialOffer::query()
            ->where('id', $offer->id)
            ->update([
                'status' => 'pending',
                'locked_at' => now(),
                'payment_id' => $payment->id,
                'payment_link' => $paymentLink,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new \Exception('Не удалось заблокировать КП после генерации ссылки оплаты.');
        }

        $offer->offerStatuses()
            ->where('status', 'pending')
            ->update([
                'status' => 'canceled',
            ]);

        $offer->offerStatuses()->create([
            'status' => 'pending',
            'status_date' => now()->toDateString(),
            'payment_method' => $paymentMethod,
            'author_id' => Auth::id(),
            'account_id' => $paymentMethod === 'invoice' ? $statusAccountId : null,
        ]);
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

    private function toDecimal($value): float
    {
        if (is_array($value)) {
            return 0.0;
        }

        $normalized = preg_replace('/\s+/', '', str_replace(',', '.', (string) $value));
        if ($normalized === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            return 0.0;
        }

        return round((float) $normalized, 4);
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
