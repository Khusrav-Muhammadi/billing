<?php

namespace App\Services\ConnectedClientServices;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\ConnectedClientServices;
use App\Support\CurrencyResolver;
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

                $periodTotal = round((float) $item->total_price, 2);
                if ($periodTotal <= 0) {
                    continue;
                }

                // Store monthly totals in registry to support day-closing daily accrual.
                $monthlyTotal = round($periodTotal / $months, 2);
                $monthlyUnitPrice = $quantity > 0 ? round($monthlyTotal / $quantity, 2) : 0.0;

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
                        $payableAmount = round($payableAmount / $rate, 2);
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
                    'date' => $offer->status_date,
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
}
