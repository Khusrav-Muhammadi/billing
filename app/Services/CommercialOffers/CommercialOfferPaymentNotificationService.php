<?php

namespace App\Services\CommercialOffers;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\ConnectedClientServices;
use App\Models\Currency;
use App\Models\ImplementationCurrencyRegistry;
use App\Services\Mailing\ResendMailService;
use Illuminate\Support\Facades\Log;

class CommercialOfferPaymentNotificationService
{
    public function __construct(
        private ResendMailService $mailService
    ) {
    }

    public function send(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string)$status->status !== 'paid') {
            return;
        }

        try {
            $offer->loadMissing([
                'organization:id,name,email,client_id',
                'organization.client:id,name,email',
                'items.tariff:id,name,is_tariff,is_extra_user',
                'partner:id,name,email',
            ]);

            $email = $this->resolveClientEmail($offer);
            if ($email === null) {
                Log::warning('CommercialOfferPaymentNotificationService: client email is empty', [
                    'commercial_offer_id' => $offer->id,
                    'organization_id' => $offer->organization_id,
                ]);
                return;
            }

            $this->mailService->sendWithView(
                to: $email,
                subject: $this->subject($offer),
                view: 'mail.commercial_offer_paid',
                data: [
                    'offer' => $offer,
                    'clientName' => $this->resolveClientName($offer),
                    'operationLabel' => $this->operationLabel($offer),
                    'services' => $this->connectedServices($offer),
                    'implementation' => $this->implementation($offer),
                    'paidAt' => $status->status_date ?: now(),
                ],
                sendInternalCopy: false,
                logContext: [
                    'organization_id' => $offer->organization_id,
                    'client_id' => $offer->organization?->client_id,
                    'commercial_offer_id' => $offer->id,
                    'action' => 'commercial_offer_paid_email',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('CommercialOfferPaymentNotificationService: failed to send client email', [
                'commercial_offer_id' => $offer->id,
                'organization_id' => $offer->organization_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveClientEmail(CommercialOffer $offer): ?string
    {
        $partnerId = (int) ($offer->partner_id ?? 0);
        $email = '';
        if ($partnerId > 0 && $partnerId !== 11) {
            $email = trim((string) ($offer->partner?->email ?? ''));
            if ($email === '') {
                $email = trim((string) ($offer->partner_email ?? ''));
            }
        }

        if ($email === '') {
            $email = trim((string)($offer->organization?->client?->email ?: $offer->client_email ?: $offer->organization?->email));
        }

        return $email !== '' ? $email : null;
    }

    private function resolveClientName(CommercialOffer $offer): string
    {
        $partnerId = (int) ($offer->partner_id ?? 0);
        $name = '';
        if ($partnerId > 0 && $partnerId !== 11) {
            $name = trim((string) ($offer->partner?->name ?? ''));
            if ($name === '') {
                $name = trim((string) ($offer->partner_name ?? ''));
            }
        }

        if ($name === '') {
            $name = trim((string)($offer->organization?->name ?: $offer->client_name ?: $offer->organization?->client?->name));
        }

        return $name !== '' ? $name : 'клиент';
    }

    private function subject(CommercialOffer $offer): string
    {
        return 'Спасибо за оплату в shamCRM';
    }

    private function operationLabel(CommercialOffer $offer): string
    {
        return match ((string)$offer->request_type) {
            'connection_extra_services' => 'подключение дополнительных услуг',
            'renewal' => 'продление с изменениями',
            'renewal_no_changes' => 'продление',
            default => 'подключение',
        };
    }

    private function connectedServices(CommercialOffer $offer): array
    {
        $items = $offer->items()
            ->with('tariff:id,name,is_tariff,is_extra_user')
            ->orderBy('id')
            ->get();

        if ($items->isNotEmpty()) {
            $currency = strtoupper((string)($offer->currency ?: $offer->payable_currency ?: ''));

            return $items
                ->map(function ($item) use ($currency): array {
                    $amount = (float)($item->total_price ?? 0);
                    $months = max(1, (int)($item->months ?? 1));
                    $quantity = max(1, (float)($item->quantity ?? 1));

                    return [
                        'name' => (string)($item->tariff?->name ?? 'Услуга'),
                        'quantity' => $this->formatQuantity($quantity),
                        'months' => (string)$months,
                        'amount' => $amount,
                        'currency' => $currency,
                        'formatted_amount' => $this->formatMoney($amount, $currency),
                    ];
                })
                ->values()
                ->all();
        }

        return ConnectedClientServices::query()
            ->where('commercial_offer_id', (int)$offer->id)
            ->with([
                'tariff:id,name,is_tariff,is_extra_user',
                'offerCurrency:id,name,symbol_code',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (ConnectedClientServices $service): array {
                $amount = (float)($service->service_total_amount ?? 0);
                $currency = strtoupper((string)($service->offerCurrency?->symbol_code ?? ''));

                return [
                    'name' => (string)($service->tariff?->name ?? 'Услуга'),
                    'quantity' => $this->formatQuantity(max(1, (float)($service->quantity ?? 1))),
                    'months' => '-',
                    'amount' => $amount,
                    'currency' => $currency,
                    'formatted_amount' => $this->formatMoney($amount, $currency),
                ];
            })
            ->values()
            ->all();
    }

    private function formatQuantity(float $quantity): string
    {
        if (floor($quantity) === $quantity) {
            return (string)(int)$quantity;
        }

        return rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
    }

    private function implementation(CommercialOffer $offer): ?array
    {
        $registry = ImplementationCurrencyRegistry::query()
            ->where('commercial_offer_id', (int)$offer->id)
            ->first();

        if (!$registry) {
            return null;
        }

        $currencyCode = Currency::query()
            ->whereKey((int)$registry->offer_currency_id)
            ->value('symbol_code');
        $currencyCode = strtoupper((string)$currencyCode);

        return [
            'name' => 'Внедрение и обучение',
            'base_amount' => (float)$registry->base_amount,
            'discount_percent' => (float)$registry->discount_percent,
            'discount_amount' => (float)$registry->discount_amount,
            'extra_amount' => (float)$registry->extra_amount,
            'total_amount' => (float)$registry->total_amount,
            'currency' => $currencyCode,
            'formatted_total' => $this->formatMoney((float)$registry->total_amount, $currencyCode),
        ];
    }

    private function formatMoney(float $amount, string $currencyCode): string
    {
        $formatted = number_format($amount, 2, ',', ' ');

        return match (strtoupper($currencyCode)) {
            'USD' => '$' . $formatted,
            'EUR' => '€' . $formatted,
            default => $formatted . ($currencyCode !== '' ? ' ' . strtoupper($currencyCode) : ''),
        };
    }
}
