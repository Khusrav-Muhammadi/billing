<?php

namespace App\Services\DiscountExpenses;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\DiscountExpense;
use Illuminate\Support\Facades\DB;

class DiscountExpensesRegistryService
{
    public function register(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string) $status->status !== 'paid') {
            return;
        }

        $requestType = (string) data_get($offer->snapshot, 'request_type', 'connection');
        if ($requestType !== 'connection') {
            return;
        }

        $offer->loadMissing(['items']);

        DB::transaction(function () use ($offer, $status) {
            DiscountExpense::query()
                ->where('commercial_offer_id', $offer->id)
                ->delete();

            foreach ($offer->items as $item) {
                $discountPercent = round(max(0, (float) $item->discount_percent), 2);
                if ($discountPercent <= 0) {
                    continue;
                }

                $currencyCode = (string) data_get($item->meta, 'source_currency', (string) $offer->currency);
                $discountedAmount = $this->resolveDiscountedAmount($item, $offer);
                if ($discountedAmount <= 0) {
                    continue;
                }

                $originalAmount = $this->calculateOriginalAmount($discountedAmount, $discountPercent);
                $discountAmount = round(max(0, $originalAmount - $discountedAmount), 2);
                if ($discountAmount <= 0) {
                    continue;
                }

                $resolvedTariffId = $this->resolveTariffId($item->service_key, $offer->tariff_id);
                if ($resolvedTariffId === null) {
                    continue;
                }

                DiscountExpense::query()->create([
                    'offer_date' => $offer->pricing_date
                        ? $offer->pricing_date->toDateString()
                        : ($status->status_date ? $status->status_date->toDateString() : now()->toDateString()),
                    'client_id' => (int) $offer->organization_id,
                    'partner_id' => $offer->partner_id ? (int) $offer->partner_id : null,
                    'tariff_id' => $resolvedTariffId,
                    'service_key' => $item->service_key,
                    'commercial_offer_id' => (int) $offer->id,
                    'commercial_offer_item_id' => (int) $item->id,
                    'discount_amount' => $discountAmount,
                    'original_amount' => $originalAmount,
                    'discount_percent' => $discountPercent,
                    'currency_code' => strtoupper(trim($currencyCode)),
                ]);
            }
        });
    }

    private function resolveDiscountedAmount($item, CommercialOffer $offer): float
    {
        $sourceTotal = (float) data_get($item->meta, 'source_price', 0);
        if ($sourceTotal > 0) {
            return round($sourceTotal, 2);
        }

        $sourceCurrency = strtoupper((string) data_get($item->meta, 'source_currency', ''));
        $offerCurrency = strtoupper((string) $offer->currency);
        if ($sourceCurrency !== '' && $sourceCurrency === $offerCurrency && (float) $item->total_price > 0) {
            return round((float) $item->total_price, 2);
        }

        return 0.0;
    }

    private function calculateOriginalAmount(float $discountedAmount, float $discountPercent): float
    {
        if ($discountPercent <= 0 || $discountPercent >= 100) {
            return round($discountedAmount, 2);
        }

        $coefficient = 1 - ($discountPercent / 100);
        if ($coefficient <= 0) {
            return round($discountedAmount, 2);
        }

        return round($discountedAmount / $coefficient, 2);
    }

    private function resolveTariffId(?string $serviceKey, ?int $fallbackTariffId): ?int
    {
        $normalizedKey = trim((string) $serviceKey);
        if ($normalizedKey !== '') {
            if (preg_match('/^(?:tariff|service)-(\d+)/', $normalizedKey, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        if ($fallbackTariffId) {
            return (int) $fallbackTariffId;
        }

        return null;
    }
}

