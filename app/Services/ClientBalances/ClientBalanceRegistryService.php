<?php

namespace App\Services\ClientBalances;

use App\Models\ClientBalance;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Support\CurrencyResolver;
use Illuminate\Support\Facades\DB;

class ClientBalanceRegistryService
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

        $offer->loadMissing(['items']);
        $grossAmount = $this->calculateGrossAmount($offer);

        DB::transaction(function () use ($offer, $grossAmount) {
            ClientBalance::query()->create([
                'date' => $offer->status_date,
                'organization_id' => (int) $offer->organization_id,
                'sum' => $grossAmount,
                'currency_id' => CurrencyResolver::idFromCode((string) $offer->currency),
                'type' => 'income',
            ]);
        });
    }

    private function calculateGrossAmount(CommercialOffer $offer): float
    {
        $gross = 0.0;

        foreach ($offer->items as $item) {
            $netSourceAmount = round((float) $item->total_price, 6);
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
