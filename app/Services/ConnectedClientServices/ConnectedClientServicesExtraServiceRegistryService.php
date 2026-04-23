<?php

namespace App\Services\ConnectedClientServices;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\ConnectedClientServices;
use App\Support\CurrencyResolver;
use App\Support\RegistryDateTimeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConnectedClientServicesExtraServiceRegistryService
{
    public function register(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string)$status->status !== 'paid') {
            return;
        }

        $requestType = (string)($offer->request_type ?: 'connection');
        if (!in_array($requestType, ['connection', 'connection_extra_services', 'renewal', 'renewal_no_changes'], true)) {
            return;
        }

        $offer->loadMissing(['items']);

        if ($offer->items->isEmpty()) {
            return;
        }

        $statusDateTime = RegistryDateTimeResolver::resolve($offer, $status);
        $hasDeactivatedAtColumn = Schema::hasColumn('connected_client_services', 'deactivated_at');

        DB::transaction(function () use ($offer, $status, $statusDateTime, $hasDeactivatedAtColumn) {


            foreach ($offer->items as $item) {

                $quantity = max(1, (float)$item->quantity);
                $months = max(1, (int)($item->months ?? 1));

                $periodNetTotal = round((float)$item->total_price, 4);
                if ($periodNetTotal <= 0) {
                    continue;
                }

                // Store monthly list totals (before period discounts) in registry to support day-closing daily accrual.
                $discountPercent = round(max(0, (float)$item->discount_percent), 4);
                $periodGrossTotal = round($this->reversePercent($periodNetTotal, $discountPercent), 4);
                $monthlyTotal = round($periodGrossTotal / $months, 4);

                $offerCurrencyCode = (string)($offer->currency ?: ($offer->payable_currency ?: 'USD'));
                $payableCurrencyCode = (string)($offer->payable_currency ?: $offerCurrencyCode);

                $offerCurrencyId = CurrencyResolver::idFromCode($offerCurrencyCode);
                $payableCurrencyId = CurrencyResolver::idFromCode($payableCurrencyCode);

                // `payable_amount` stores monthly total in payable currency (for reporting/controls),
                // not per-unit price.
                $payableAmount = $monthlyTotal;
                if ($payableCurrencyCode !== $offerCurrencyCode) {
                    $rate = (float)($offer->conversion_rate ?? 0);
                    if ($rate > 0) {
                        $payableAmount = round($payableAmount / $rate, 4);
                    }
                }

                ConnectedClientServices::query()->create([
                    'client_id' => $offer->organization_id,
                    'partner_id' => $offer->partner_id,
                    'tariff_id' => $item->tariff_id ?: $offer->tariff_id,
                    'commercial_offer_id' => (int)$offer->id,
                    'account_id' => $status->account_id,
                    'service_total_amount' => $monthlyTotal,
                    'status' => true,
                    'date' => $statusDateTime,
                    'offer_currency_id' => $offerCurrencyId,
                    'payable_currency_id' => $payableCurrencyId,
                    'payable_amount' => $payableAmount,
                ]);
            }
        });
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
