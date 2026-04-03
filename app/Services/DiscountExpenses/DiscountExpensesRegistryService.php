<?php

namespace App\Services\DiscountExpenses;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\DiscountExpense;
use App\Support\CurrencyResolver;
use Illuminate\Support\Facades\DB;

class DiscountExpensesRegistryService
{
    public function register(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string) $status->status !== 'paid') {
            return;
        }

        $requestType = (string) ($offer->request_type ?: 'connection');
        if (!in_array($requestType, ['connection', 'connection_extra_services'], true)) {
            return;
        }

        $offer->loadMissing(['items']);

        DB::transaction(function () use ($offer, $status) {

            foreach ($offer->items as $item) {
                $discountPercent = round(max(0, (float) $item->discount_percent), 2);
                if ($discountPercent <= 0) {
                    continue;
                }

                $currencyId = CurrencyResolver::idFromCode((string) $offer->currency);
                $discountedAmount = $this->resolveDiscountedAmount($item);
                if ($discountedAmount <= 0) {
                    continue;
                }

                $originalAmount = $this->calculateOriginalAmount($discountedAmount, $discountPercent);
                $discountAmount = round(max(0, $originalAmount - $discountedAmount), 2);
                if ($discountAmount <= 0) {
                    continue;
                }

                $resolvedTariffId = $item->tariff_id ? (int) $item->tariff_id : ($offer->tariff_id ? (int) $offer->tariff_id : null);
                if ($resolvedTariffId === null) {
                    continue;
                }

                $attributes = [
                    'date' => $offer->status_date,
                    'client_id' => (int) $offer->organization_id,
                    'partner_id' => $offer->partner_id ? (int) $offer->partner_id : null,
                    'tariff_id' => $resolvedTariffId,
                    'discount_amount' => $discountAmount,
                    'original_amount' => $originalAmount,
                    'discount_percent' => $discountPercent,
                    'currency_id' => $currencyId,
                ];

                DiscountExpense::query()->updateOrCreate($attributes, $attributes);
            }
        });
    }

    private function resolveDiscountedAmount($item): float
    {
        $itemTotal = (float) $item->total_price;
        return $itemTotal > 0 ? round($itemTotal, 2) : 0.0;
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


}
