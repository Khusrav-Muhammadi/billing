<?php

namespace App\Services\PartnerExpenses;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\PartnerProcent;
use App\Models\PartnerExpense;
use App\Support\CurrencyResolver;
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

        $requestType = (string) ($offer->request_type ?: 'connection');
        if (!in_array($requestType, ['connection', 'connection_extra_services', 'renewal', 'renewal_no_changes'], true)) {
            return;
        }

        $offer->loadMissing(['items']);

        DB::transaction(function () use ($offer, $status, $requestType) {

            foreach ($offer->items as $item) {
                $partnerPercent = $this->resolvePartnerPercent($offer, $item, $status);

                if ($partnerPercent <= 0) {
                    continue;
                }

                $currencyId = CurrencyResolver::idFromCode((string) $offer->currency);
                $grossAmount = $this->resolveDiscountedAmount($item);
                if ($grossAmount <= 0) {
                    continue;
                }

                // Partner percent is a commission share, not a discount for the client.
                // `total_price` already contains the amount paid by the client (after period discount, if any).
                $originalAmount = round($grossAmount, 2);
                $partnerAmount = round(max(0, $originalAmount * ($partnerPercent / 100)), 2);
                if ($partnerAmount <= 0) {
                    continue;
                }

                $resolvedTariffId = $item->tariff_id ? (int) $item->tariff_id : ($offer->tariff_id ? (int) $offer->tariff_id : null);
                if ($resolvedTariffId === null) {
                    continue;
                }

                $attributes = [
                    'partner_id' => (int) $offer->partner_id,
                    'client_id' => (int) $offer->organization_id,
                    'date' => $offer->status_date,
                    'tariff_id' => $resolvedTariffId,
                    'partner_amount' => $partnerAmount,
                    'original_amount' => $originalAmount,
                    'partner_percent' => $partnerPercent,
                    'currency_id' => $currencyId,
                    'request_type' => $requestType,
                ];

                PartnerExpense::query()->updateOrCreate($attributes, $attributes);
            }
        });
    }

    private function resolveDiscountedAmount($item): float
    {
        $itemTotal = (float) $item->total_price;
        return $itemTotal > 0 ? round($itemTotal, 2) : 0.0;
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

    private function resolvePartnerPercent(CommercialOffer $offer, $item, CommercialOfferStatus $status): float
    {
        $itemPercent = round(max(0, (float) $item->partner_percent), 2);
        if ($itemPercent > 0) {
            return $itemPercent;
        }

        if (!$offer->partner_id) {
            return 0.0;
        }

        if (!$offer->status_date) {
            return 0.0;
        }

        $asOf = $offer->status_date->toDateString();

        $row = PartnerProcent::query()
            ->where('partner_id', (int) $offer->partner_id)
            ->whereDate('date', '<=', $asOf)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['procent_from_tariff']);

        if (!$row) {
            return 0.0;
        }

        $percent = round(max(0, (float) ($row->procent_from_tariff ?? 0)), 2);

        return min(100.0, $percent);
    }

}
