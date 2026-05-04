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
        $email = trim((string)($offer->organization?->client?->email ?: $offer->client_email ?: $offer->organization?->email));

        return $email !== '' ? $email : null;
    }

    private function resolveClientName(CommercialOffer $offer): string
    {
        $name = trim((string)($offer->organization?->name ?: $offer->client_name ?: $offer->organization?->client?->name));

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
                    'quantity' => max(1, (int)($service->quantity ?? 1)),
                    'amount' => $amount,
                    'currency' => $currency,
                    'formatted_amount' => $this->formatMoney($amount, $currency),
                ];
            })
            ->values()
            ->all();
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
