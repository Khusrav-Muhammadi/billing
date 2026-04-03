<?php

namespace App\Services\ClientPaymentRegistries;

use App\Models\ClientPaymentRegistry;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Support\CurrencyResolver;
use Illuminate\Support\Facades\DB;

class ClientPaymentRegistryService
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

        $grossAmount = $this->calculateGrossAmount($offer);
        $netAmount = round((float) $offer->grand_total, 2);
        $tariffAmount = $netAmount;
        $paymentAmount = round((float) $offer->payable_total, 2);

        DB::transaction(function () use (
            $offer,
            $requestType,
            $grossAmount,
            $netAmount,
            $tariffAmount,
            $paymentAmount,
            $status
        ) {
            ClientPaymentRegistry::query()
                ->where('commercial_offer_id', $offer->id)
                ->delete();

            ClientPaymentRegistry::query()->create([
                'date' => $offer->status_date,
                'organization_id' => (int) $offer->organization_id,
                'commercial_offer_id' => (int) $offer->id,
                'partner_id' => $offer->partner_id ? (int) $offer->partner_id : null,
                'payment_method' => (string) $status->payment_method,
                'account_id' => $status->account_id ? (int) $status->account_id : null,
                'gross_amount' => $grossAmount,
                'net_amount' => $netAmount,
                'tariff_currency_id' => CurrencyResolver::idFromCode((string) $offer->currency),
                'tariff_amount' => $tariffAmount,
                'payment_currency_id' => CurrencyResolver::idFromCode((string) ($offer->payable_currency ?: $offer->currency)),
                'payment_amount' => $paymentAmount,
                'request_type' => $requestType,
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
