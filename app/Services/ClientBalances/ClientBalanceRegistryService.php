<?php

namespace App\Services\ClientBalances;

use App\Models\ClientBalance;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Support\CurrencyResolver;
use App\Support\RegistryDateTimeResolver;
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

        $offer->loadMissing(['items.tariff:id,is_external']);
        $incomeAmount = $this->calculateGrossAmount($offer);
        if ($incomeAmount <= 0) {
            return;
        }

        DB::transaction(function () use ($offer, $incomeAmount, $status) {
            ClientBalance::query()->create([
                'date' => RegistryDateTimeResolver::resolve($offer, $status),
                'organization_id' => (int) $offer->organization_id,
                'sum' => $incomeAmount,
                'currency_id' => CurrencyResolver::idFromCode((string) $offer->currency),
                'type' => 'income',
            ]);
        });
    }

    private function calculateGrossAmount(CommercialOffer $offer): float
    {
        $gross = 0.0;

        foreach ($offer->items as $item) {
            if ($this->isExternalItem($item)) {
                continue;
            }

            $netSourceAmount = round((float) $item->total_price, 6);
            if ($netSourceAmount <= 0) {
                continue;
            }

            $discountPercent = round(max(0, (float) $item->discount_percent), 4);
            $grossLine = $this->reversePercent($netSourceAmount, $discountPercent);
            $gross += $grossLine;
        }

        return round($gross, 4);
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

    private function isExternalItem($item): bool
    {
        return (bool) ($item?->tariff?->is_external ?? false);
    }
}
