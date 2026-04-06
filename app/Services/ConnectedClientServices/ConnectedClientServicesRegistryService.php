<?php

namespace App\Services\ConnectedClientServices;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\ConnectedClientServices;
use App\Support\CurrencyResolver;
use App\Support\RegistryDateTimeResolver;
use Illuminate\Support\Facades\DB;

class ConnectedClientServicesRegistryService
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

        if ($offer->items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($offer, $status) {
            ConnectedClientServices::query()
                ->where('commercial_offer_id', $offer->id)
                ->update(['status' => false]);

            if ($this->shouldDeactivatePreviousForRenewal($offer)) {
                ConnectedClientServices::query()
                    ->where('client_id', (int) $offer->organization_id)
                    ->where('status', true)
                    ->where('commercial_offer_id', '!=', (int) $offer->id)
                    ->update(['status' => false]);
            }

            foreach ($offer->items as $item) {

                $quantity = max(1, (float) $item->quantity);
                $months = max(1, (int) ($item->months ?? 1));

                $periodNetTotal = round((float) $item->total_price, 4);
                if ($periodNetTotal <= 0) {
                    continue;
                }

                // Store monthly list totals (before period discounts) in registry to support day-closing daily accrual.
                $discountPercent = round(max(0, (float) $item->discount_percent), 4);
                $periodGrossTotal = round($this->reversePercent($periodNetTotal, $discountPercent), 4);
                $monthlyTotal = round($periodGrossTotal / $months, 4);

                $offerCurrencyCode = (string) ($offer->currency ?: ($offer->payable_currency ?: 'USD'));
                $payableCurrencyCode = (string) ($offer->payable_currency ?: $offerCurrencyCode);

                $offerCurrencyId = CurrencyResolver::idFromCode($offerCurrencyCode);
                $payableCurrencyId = CurrencyResolver::idFromCode($payableCurrencyCode);

                // `payable_amount` stores monthly total in payable currency (for reporting/controls),
                // not per-unit price.
                $payableAmount = $monthlyTotal;
                if ($payableCurrencyCode !== $offerCurrencyCode) {
                    $rate = (float) ($offer->conversion_rate ?? 0);
                    if ($rate > 0) {
                        $payableAmount = round($payableAmount / $rate, 4);
                    }
                }

                ConnectedClientServices::query()->create([
                    'client_id' => $offer->organization_id,
                    'partner_id' => $offer->partner_id,
                    'tariff_id' => $item->tariff_id ?: $offer->tariff_id,
                    'commercial_offer_id' => (int) $offer->id,
                    'account_id' => $status->account_id,
                    'service_total_amount' => $monthlyTotal,
                    'status' => true,
                    'date' => RegistryDateTimeResolver::resolve($offer, $status),
                    'offer_currency_id' => $offerCurrencyId,
                    'payable_currency_id' => $payableCurrencyId,
                    'payable_amount' => $payableAmount,
                ]);
            }
        });
    }

    private function shouldDeactivatePreviousForRenewal(CommercialOffer $offer): bool
    {
        $requestType = (string) ($offer->request_type ?: '');
        if (!in_array($requestType, ['renewal', 'renewal_no_changes'], true)) {
            return false;
        }

        if ($requestType === 'renewal_no_changes') {
            return true;
        }

        if (!$offer->status_date) {
            return false;
        }

        return $offer->status_date->toDateString() === now()->toDateString();
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
