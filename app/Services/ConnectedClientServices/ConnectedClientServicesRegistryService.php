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
        if (!in_array($requestType, ['connection', 'connection_extra_services'], true)) {
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

            foreach ($offer->items as $item) {

                $quantity = max(1, (float) $item->quantity);
                $unitPrice = (float) $item->unit_price;
                if ($unitPrice <= 0) {
                    $unitPrice = $quantity > 0
                        ? ((float) $item->total_price / $quantity)
                        : (float) $item->total_price;
                }

                $serviceTotalAmount = round($quantity * $unitPrice, 2);
                $payableAmount = round((float) $item->total_price, 2);

                ConnectedClientServices::query()->create([
                    'client_id' => $offer->organization_id,
                    'partner_id' => $offer->partner_id,
                    'tariff_id' => $item->tariff_id ?: $offer->tariff_id,
                    'commercial_offer_id' => (int) $offer->id,
                    'account_id' => $status->account_id,
                    'service_total_amount' => $serviceTotalAmount,
                    'status' => true,
                    'date' => $offer->status_date,
                    'offer_currency_id' => CurrencyResolver::idFromCode((string) $offer->currency),
                    'payable_currency_id' => CurrencyResolver::idFromCode((string) ($offer->payable_currency ?: $offer->currency)),
                    'payable_amount' => $payableAmount,
                ]);
            }
        });
    }
}
