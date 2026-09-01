<?php

namespace App\Services\Site;

use App\Models\Ai\AiBalance;
use App\Models\CommercialOffer;
use App\Models\ConnectedClientServices;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Services\Payment\OnlineCheckoutLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteOfferCheckoutService
{
    public function __construct(
        private readonly SitePaymentLinkService $cart,
        private readonly SiteOrganizationContextService $organizationContext,
    ) {
    }

    public function connection(array $payload): array
    {
        return $this->checkout($payload, 'connection', true);
    }

    public function renewal(array $payload): array
    {
        $this->assertHasActiveTariff((int) ($payload['organization_id'] ?? 0));

        return $this->checkout($payload, 'renewal', true);
    }

    public function extraServices(array $payload): array
    {
        $tariffId = $this->assertHasActiveTariff((int) ($payload['organization_id'] ?? 0));
        $payload['tariff_id'] = $tariffId;
        $payload['extra_users'] = (int) ($payload['extra_users'] ?? 0);

        return $this->checkout($payload, 'connection_extra_services', false);
    }

    public function aiTopUp(array $payload): array
    {
        $context = $this->organizationContext->resolve((int) ($payload['organization_id'] ?? 0));
        $paymentType = $this->organizationContext->normalizePaymentType((string) ($payload['payment_type'] ?? ''));
        $this->organizationContext->assertPaymentTypeAllowed($context, $paymentType);

        $amount = round((float) ($payload['amount'] ?? 0), 4);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма должна быть больше 0.',
            ]);
        }

        $organization = $context['organization'];
        $payer = $context['payer'];
        $currency = $paymentType === 'visa' ? 'USD' : (string) $context['currency'];
        $this->ensureAiBalance((int) $organization->id, (string) $context['currency']);

        $payload['period_months'] = 1;
        $payload['extra_users'] = 0;
        $payload['tariff_id'] = 0;

        $quote = [
            'context' => $context,
            'payer' => $payer,
            'payment_type' => $paymentType,
            'currency' => $currency,
            'period_months' => 1,
            'tariff_id' => 0,
            'extra_users' => 0,
            'items' => [[
                'id' => 0,
                'type' => 'ai_topup',
                'name' => 'Пополнение ИИ-счёта',
                'quantity' => 1,
                'unit_price' => $amount,
                'price' => $amount,
                'discount_percent' => 0,
                'months' => 1,
            ]],
            'sum' => $amount,
        ];

        return $this->checkoutFromQuote($quote, 'ai_topup');
    }

    private function checkout(array $payload, string $requestType, bool $includeTariff): array
    {
        return $this->checkoutFromQuote($this->cart->quote($payload, $includeTariff), $requestType);
    }

    private function checkoutFromQuote(array $quote, string $requestType): array
    {
        $context = $quote['context'];
        $organization = $context['organization'];
        $payer = $quote['payer'];
        $paymentType = $quote['payment_type'];
        $currency = $quote['currency'];
        $periodMonths = $quote['period_months'];
        $items = $quote['items'];
        $sum = $quote['sum'];
        $storedPaymentType = $paymentType === 'visa' ? 'octo' : $paymentType;

        $result = DB::transaction(function () use (
            $organization,
            $payer,
            $quote,
            $requestType,
            $paymentType,
            $storedPaymentType,
            $currency,
            $periodMonths,
            $items,
            $sum
        ) {
            $offer = new CommercialOffer();
            $offer->fill([
                'organization_id' => $organization->id,
                'partner_id' => null,
                'tariff_id' => $quote['tariff_id'] ?: null,
                'created_by' => null,
                'status' => 'pending',
                'request_type' => $requestType,
                'saved_at' => now(),
                'locked_at' => now(),
                'pricing_date' => now()->toDateString(),
                'currency' => $currency,
                'payable_currency' => $currency,
                'card_payment_type' => $paymentType === 'alif' ? 'alif' : 'octo',
                'period_months' => $periodMonths,
                'extra_users' => $quote['extra_users'],
                'client_name' => $payer['name'],
                'client_phone' => $payer['phone'],
                'client_email' => $payer['email'],
                'partner_name' => null,
                'partner_phone' => null,
                'partner_email' => null,
                'payer_type' => 'client',
                'original_total' => $sum,
                'monthly_total' => $periodMonths > 0 ? round($sum / $periodMonths, 4) : $sum,
                'period_total' => $sum,
                'grand_total' => $sum,
                'payable_total' => $sum,
            ]);
            $offer->save();

            foreach ($items as $item) {
                $tariffId = (int) ($item['id'] ?? 0);
                if ($tariffId <= 0) {
                    continue;
                }

                $offer->items()->create([
                    'tariff_id' => $tariffId,
                    'quantity' => max(1, (int) $item['quantity']),
                    'unit_price' => $item['unit_price'],
                    'months' => max(1, (int) ($item['months'] ?? $periodMonths)),
                    'discount_percent' => 0,
                    'partner_percent' => 0,
                    'total_price' => $item['price'],
                ]);
            }

            $payment = Payment::query()->create([
                'name' => $payer['name'],
                'phone' => preg_replace('/\D+/', '', (string) $payer['phone']) ?: '',
                'email' => $payer['email'],
                'sum' => $sum,
                'payment_type' => $storedPaymentType,
            ]);

            foreach ($items as $item) {
                PaymentItem::query()->create([
                    'payment_id' => $payment->id,
                    'service_name' => $item['name'],
                    'price' => $item['price'],
                ]);
            }

            $offer->forceFill([
                'payment_id' => $payment->id,
            ])->save();

            $offer->offerStatuses()->create([
                'status' => 'pending',
                'status_date' => now()->toDateString(),
                'payment_method' => $paymentType === 'invoice' ? 'invoice' : 'card',
                'author_id' => null,
            ]);

            return [
                'offer' => $offer,
                'payment' => $payment,
            ];
        });

        $payment = $result['payment']->fresh('paymentItems');
        $providerUrl = $paymentType === 'invoice'
            ? null
            : app(OnlineCheckoutLinkService::class)->createUrl($payment);

        $publicUrl = url('/payment/' . $payment->id);

        $result['offer']->forceFill([
            'payment_link' => $providerUrl,
        ])->save();

        return [
            'offer_id' => (int) $result['offer']->id,
            'payment_id' => (int) $payment->id,
            'organization_id' => (int) $organization->id,
            'request_type' => $requestType,
            'payer' => $payer,
            'payment_type' => $paymentType,
            'currency' => $currency,
            'period_months' => $periodMonths,
            'sum' => $sum,
            'payment_url' => $publicUrl,
            'redirect_url' => $publicUrl,
            'items' => $items,
        ];
    }

    private function assertHasActiveTariff(int $organizationId): int
    {
        $context = $this->organizationContext->resolve($organizationId);
        $organization = $context['organization'];

        $row = ConnectedClientServices::query()
            ->where('client_id', $organization->id)
            ->where('status', true)
            ->whereNull('deactivated_at')
            ->whereHas('tariff', function ($query) {
                $query->where('is_tariff', true)
                    ->where(function ($inner) {
                        $inner->whereNull('is_extra_user')->orWhere('is_extra_user', false);
                    });
            })
            ->orderByDesc('id')
            ->first(['id', 'tariff_id']);

        if (!$row) {
            throw ValidationException::withMessages([
                'organization_id' => 'У организации нет активного тарифа. Сначала оформите подключение.',
            ]);
        }

        return (int) $row->tariff_id;
    }

    private function ensureAiBalance(int $organizationId, string $currencyCode): void
    {
        $currencyId = (int) Currency::query()
            ->whereRaw('UPPER(symbol_code) = ?', [strtoupper($currencyCode)])
            ->value('id');

        if ($currencyId <= 0) {
            throw ValidationException::withMessages([
                'organization_id' => 'Не удалось определить валюту для ИИ-баланса.',
            ]);
        }

        AiBalance::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'currency_id' => $currencyId,
                'limited_balance' => 0,
                'ai_balance' => 0,
                'is_agent_enabled' => false,
            ]
        );
    }
}
