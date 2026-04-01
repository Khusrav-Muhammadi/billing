<?php

namespace App\Services\ClientBalances;

use App\Models\ClientBalance;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use Illuminate\Support\Facades\DB;

class ClientBalanceRegistryService
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
        $grossAmount = $this->calculateGrossAmount($offer);

        DB::transaction(function () use ($offer, $grossAmount) {
            ClientBalance::query()
                ->where('commercial_offer_id', $offer->id)
                ->delete();

            ClientBalance::query()->create([
                'organization_id' => (int) $offer->organization_id,
                'commercial_offer_id' => (int) $offer->id,
                'sum' => $grossAmount,
                'currency' => strtoupper((string) $offer->currency),
                'type' => 'income',
            ]);
        });
    }

    private function calculateGrossAmount(CommercialOffer $offer): float
    {
        $gross = 0.0;
        $conversionRate = (float) $offer->conversion_rate;

        foreach ($offer->items as $item) {
            $netSourceAmount = $this->resolveNetSourceAmount($offer, $item, $conversionRate);
            if ($netSourceAmount <= 0) {
                continue;
            }

            $discountPercent = round(max(0, (float) $item->discount_percent), 4);
            $partnerPercent = round(max(0, (float) $item->partner_percent), 4);

            $grossLine = $netSourceAmount;
            $grossLine = $this->reversePercent($grossLine, $partnerPercent);
            $grossLine = $this->reversePercent($grossLine, $discountPercent);
            $gross += $grossLine;
        }

        if ($gross <= 0) {
            return round((float) $offer->grand_total, 2);
        }

        return round($gross, 2);
    }

    private function resolveNetSourceAmount(CommercialOffer $offer, $item, float $conversionRate): float
    {
        $metaSource = (float) data_get($item->meta, 'source_price', 0);
        if ($metaSource > 0) {
            return round($metaSource, 6);
        }

        $itemTotal = (float) $item->total_price;
        if ($itemTotal <= 0) {
            return 0.0;
        }

        $sourceCurrency = strtoupper((string) data_get($item->meta, 'source_currency', $offer->currency));
        $payableCurrency = strtoupper((string) data_get($item->meta, 'payable_currency', ($offer->payable_currency ?: $offer->currency)));
        $offerCurrency = strtoupper((string) $offer->currency);

        if ($sourceCurrency === $offerCurrency) {
            return round($itemTotal, 6);
        }

        if ($payableCurrency !== $offerCurrency && $conversionRate > 0) {
            return round($itemTotal * $conversionRate, 6);
        }

        return round($itemTotal, 6);
    }

    private function reversePercent(float $amount, float $percent): float
    {
        if ($percent <= 0 || $percent >= 100) {
            return $amount;
        }

        $factor = 1 - ($percent / 100);
        if ($factor <= 0) {
            return $amount;
        }

        return $amount / $factor;
    }
}

