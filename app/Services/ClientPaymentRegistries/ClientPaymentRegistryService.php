<?php

namespace App\Services\ClientPaymentRegistries;

use App\Models\ClientPaymentRegistry;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Support\CurrencyResolver;
use App\Support\RegistryDateTimeResolver;
use Illuminate\Support\Facades\DB;

class ClientPaymentRegistryService
{
    public function register(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string) $status->status !== 'paid') {
            return;
        }

        $requestType = (string) ($offer->request_type ?: 'connection');
        if (!in_array($requestType, ['connection', 'connection_extra_services', 'renewal', 'renewal_no_changes'], true)) {
            return;
        }

        $offer->loadMissing(['items.tariff:id,is_external']);

        $externalAmount = $this->calculateExternalAmount($offer);
        $externalPartnerAmount = $this->calculateExternalPartnerExpenseAmount($offer);
        $grossAmount = round(max(0, (float) $offer->grand_total - $externalAmount), 4);
        if ($grossAmount <= 0) {
            ClientPaymentRegistry::query()
                ->where('commercial_offer_id', $offer->id)
                ->delete();
            return;
        }

        $partnerAmount = $this->calculatePartnerExpenseAmount($offer);
        $netAmount = round(max(0, $grossAmount - $partnerAmount), 4);
        $tariffAmount = $netAmount;
        $externalPaymentSourceAmount = round(max(0, $externalAmount - $externalPartnerAmount), 4);
        $paymentAmount = round(
            max(0, (float) $offer->payable_total - $this->convertSourceToPayable($offer, $externalPaymentSourceAmount)),
            4
        );

        DB::transaction(function () use (
            $offer,
            $requestType,
            $grossAmount,
            $netAmount,
            $tariffAmount,
            $paymentAmount,
            $status
        ) {
            ClientPaymentRegistry::query()
                ->where('commercial_offer_id', $offer->id)
                ->delete();

            ClientPaymentRegistry::query()->create([
                'date' => RegistryDateTimeResolver::resolve($offer, $status),
                'organization_id' => (int) $offer->organization_id,
                'commercial_offer_id' => (int) $offer->id,
                'partner_id' => $offer->partner_id ? (int) $offer->partner_id : null,
                'payment_method' => (string) $status->payment_method,
                'account_id' => $status->account_id ? (int) $status->account_id : null,
                'gross_amount' => $grossAmount,
                'net_amount' => $netAmount,
                'tariff_currency_id' => CurrencyResolver::idFromCode((string) $offer->currency),
                'tariff_amount' => $tariffAmount,
                'payment_currency_id' => CurrencyResolver::idFromCode((string) ($offer->payable_currency ?: $offer->currency)),
                'payment_amount' => $paymentAmount,
                'request_type' => $requestType,
            ]);
        });
    }

    private function calculateExternalAmount(CommercialOffer $offer): float
    {
        $sum = 0.0;

        foreach ($offer->items as $item) {
            if (!$this->isExternalItem($item)) {
                continue;
            }

            $lineAmount = round((float) $item->total_price, 6);
            if ($lineAmount <= 0) {
                continue;
            }

            $sum += $lineAmount;
        }

        return round($sum, 4);
    }

    private function calculateExternalPartnerExpenseAmount(CommercialOffer $offer): float
    {
        $sum = 0.0;

        foreach ($offer->items as $item) {
            if (!$this->isExternalItem($item)) {
                continue;
            }

            $lineAmount = round((float) $item->total_price, 6);
            if ($lineAmount <= 0) {
                continue;
            }

            $partnerPercent = round(max(0, (float) $item->partner_percent), 4);
            if ($partnerPercent <= 0) {
                continue;
            }

            $sum += $lineAmount * ($partnerPercent / 100);
        }

        return round($sum, 4);
    }

    private function convertSourceToPayable(CommercialOffer $offer, float $sourceAmount): float
    {
        $amount = round(max(0, $sourceAmount), 4);
        $offerCurrency = strtoupper((string) $offer->currency);
        $payableCurrency = strtoupper((string) ($offer->payable_currency ?: $offerCurrency));

        if ($amount <= 0 || $payableCurrency === $offerCurrency) {
            return $amount;
        }

        $rate = (float) ($offer->conversion_rate ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }

        return round($amount * $rate, 4);
    }

    private function calculatePartnerExpenseAmount(CommercialOffer $offer): float
    {
        $sum = 0.0;

        foreach ($offer->items as $item) {
            if ($this->isExternalItem($item)) {
                continue;
            }

            $lineAmount = round((float) $item->total_price, 6);
            if ($lineAmount <= 0) {
                continue;
            }

            $partnerPercent = round(max(0, (float) $item->partner_percent), 4);
            if ($partnerPercent <= 0) {
                continue;
            }

            $sum += $lineAmount * ($partnerPercent / 100);
        }

        return round($sum, 4);
    }

    private function isExternalItem($item): bool
    {
        return (bool) ($item?->tariff?->is_external ?? false);
    }

}
