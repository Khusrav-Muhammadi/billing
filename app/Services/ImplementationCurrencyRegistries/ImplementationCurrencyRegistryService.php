<?php

namespace App\Services\ImplementationCurrencyRegistries;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\ImplementationCurrencyRegistry;
use App\Support\CurrencyResolver;
use App\Support\RegistryDateTimeResolver;
use Illuminate\Support\Facades\DB;

class ImplementationCurrencyRegistryService
{
    public function register(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string)$status->status !== 'paid') {
            return;
        }

        $implementation = $this->resolveImplementation($offer);
        $enabled = (bool)($implementation['enabled'] ?? false);
        $extraAmount = $this->sumExtraServices($implementation['extra_services'] ?? []);

        if (!$implementation || (!$enabled && $extraAmount <= 0)) {
            ImplementationCurrencyRegistry::query()
                ->where('commercial_offer_id', (int)$offer->id)
                ->delete();

            return;
        }

        $baseAmount = $enabled ? round(max(0, (float)($implementation['price'] ?? 0)), 4) : 0.0;
        $discountPercent = $enabled ? round(max(0, min(100, (float)($implementation['discount_percent'] ?? 0))), 4) : 0.0;
        $discountAmount = round($baseAmount * ($discountPercent / 100), 4);
        $totalAmount = round(max(0, $baseAmount - $discountAmount) + $extraAmount, 4);

        if ($totalAmount <= 0) {
            ImplementationCurrencyRegistry::query()
                ->where('commercial_offer_id', (int)$offer->id)
                ->delete();

            return;
        }

        $offerCurrencyCode = strtoupper((string)($offer->currency ?: ($offer->payable_currency ?: 'USD')));
        $payableCurrencyCode = strtoupper((string)($offer->payable_currency ?: $offerCurrencyCode));
        $conversionRate = $offer->conversion_rate !== null ? (float)$offer->conversion_rate : null;
        $payableAmount = $this->resolvePayableAmount($totalAmount, $offerCurrencyCode, $payableCurrencyCode, $conversionRate);

        DB::transaction(function () use (
            $offer,
            $status,
            $baseAmount,
            $discountPercent,
            $discountAmount,
            $extraAmount,
            $totalAmount,
            $payableAmount,
            $conversionRate,
            $offerCurrencyCode,
            $payableCurrencyCode
        ): void {
            ImplementationCurrencyRegistry::query()->updateOrCreate(
                [
                    'commercial_offer_id' => (int)$offer->id,
                ],
                [
                    'date' => RegistryDateTimeResolver::resolve($offer, $status),
                    'organization_id' => (int)$offer->organization_id,
                    'partner_id' => $offer->partner_id ? (int)$offer->partner_id : null,
                    'offer_currency_id' => CurrencyResolver::idFromCode($offerCurrencyCode),
                    'payable_currency_id' => CurrencyResolver::idFromCode($payableCurrencyCode),
                    'base_amount' => $baseAmount,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'extra_amount' => $extraAmount,
                    'total_amount' => $totalAmount,
                    'payable_amount' => $payableAmount,
                    'conversion_rate' => $conversionRate,
                    'request_type' => (string)($offer->request_type ?: 'connection'),
                ]
            );
        });
    }

    private function resolveImplementation(CommercialOffer $offer): ?array
    {
        $snapshot = $offer->snapshot;
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        if (!is_array($snapshot)) {
            return null;
        }

        $implementation = $snapshot['implementation'] ?? null;

        return is_array($implementation) ? $implementation : null;
    }

    private function sumExtraServices($extraServices): float
    {
        if (!is_array($extraServices)) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($extraServices as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sum += max(0, (float)($row['price'] ?? 0));
        }

        return round($sum, 4);
    }

    private function resolvePayableAmount(float $totalAmount, string $offerCurrencyCode, string $payableCurrencyCode, ?float $conversionRate): float
    {
        if ($payableCurrencyCode === $offerCurrencyCode || !$conversionRate || $conversionRate <= 0) {
            return round($totalAmount, 4);
        }

        return round($totalAmount / $conversionRate, 4);
    }
}
