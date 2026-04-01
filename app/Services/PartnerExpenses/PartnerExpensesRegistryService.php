<?php

namespace App\Services\PartnerExpenses;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferItem;
use App\Models\CommercialOfferStatus;
use App\Models\PartnerExpense;
use Illuminate\Support\Facades\DB;

class PartnerExpensesRegistryService
{
    public function register(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string) $status->status !== 'paid') {
            return;
        }

        if (!$offer->partner_id) {
            return;
        }

        $requestType = (string) data_get($offer->snapshot, 'request_type', 'connection');
        if (!in_array($requestType, ['connection', 'connection_extra_services'], true)) {
            return;
        }

        $offer->loadMissing(['items']);

        DB::transaction(function () use ($offer, $status, $requestType) {
            PartnerExpense::query()
                ->where('commercial_offer_id', $offer->id)
                ->delete();

            foreach ($offer->items as $item) {
                $partnerPercent = round(max(0, (float) $item->partner_percent), 2);
                if ($partnerPercent <= 0) {
                    continue;
                }

                $currencyCode = (string) data_get($item->meta, 'source_currency', (string) $offer->currency);
                $discountedAmount = $this->resolveDiscountedAmount($item, $offer);
                if ($discountedAmount <= 0) {
                    continue;
                }

                $originalAmount = $this->calculateOriginalAmount($discountedAmount, $partnerPercent);
                $partnerAmount = round(max(0, $originalAmount - $discountedAmount), 2);
                if ($partnerAmount <= 0) {
                    continue;
                }

                PartnerExpense::query()->create([
                    'partner_id' => (int) $offer->partner_id,
                    'client_id' => (int) $offer->organization_id,
                    'offer_date' => $offer->pricing_date
                        ? $offer->pricing_date->toDateString()
                        : ($status->status_date ? $status->status_date->toDateString() : now()->toDateString()),
                    'service_type' => CommercialOfferItem::class,
                    'service_id' => (int) $item->id,
                    'service_key' => $item->service_key,
                    'commercial_offer_id' => (int) $offer->id,
                    'commercial_offer_item_id' => (int) $item->id,
                    'partner_amount' => $partnerAmount,
                    'original_amount' => $originalAmount,
                    'partner_percent' => $partnerPercent,
                    'currency_code' => strtoupper(trim($currencyCode)),
                    'request_type' => $requestType,
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

    private function calculateOriginalAmount(float $discountedAmount, float $percent): float
    {
        if ($percent <= 0 || $percent >= 100) {
            return round($discountedAmount, 2);
        }

        $coefficient = 1 - ($percent / 100);
        if ($coefficient <= 0) {
            return round($discountedAmount, 2);
        }

        return round($discountedAmount / $coefficient, 2);
    }
}

