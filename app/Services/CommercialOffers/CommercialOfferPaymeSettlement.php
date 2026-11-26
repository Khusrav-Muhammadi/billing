<?php

namespace App\Services\CommercialOffers;

use App\Events\CommercialOfferExtraServicesPaidStatusEvent;
use App\Events\CommercialOfferPaidStatusEvent;
use App\Events\CommercialOfferRenewalNoChangePaidStatusEvent;
use App\Events\CommercialOfferRenewalPaidStatusEvent;
use App\Models\Client;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\PaymeBalanceTransaction;
use App\Services\Payment\PaymeMerchantService;

class CommercialOfferPaymeSettlement
{
    /**
     * Помечает КП оплаченным и запускает тот же разбор, что вебхук Alif.
     * Вызывается внутри транзакции PerformTransaction, пока строка КП заблокирована.
     */
    public function settle(CommercialOffer $offer, PaymeBalanceTransaction $paymeTransaction): CommercialOfferStatus
    {
        $payment = $this->resolvePayment($offer, (int) $paymeTransaction->amount_tiyin);
        $paidAt = now();

        $payment->forceFill([
            'payment_type' => 'payme',
            'transaction_id' => $paymeTransaction->payme_id,
            'provider_status' => 'SUCCEEDED',
            'paid_at' => $paidAt,
        ])->save();

        $organization = Organization::query()->find($offer->organization_id);
        if ($organization?->client_id) {
            Client::query()->whereKey($organization->client_id)->update([
                'is_active' => 1,
                'is_demo' => 0,
            ]);
        }

        $this->syncClientPartner($offer);

        $status = $offer->offerStatuses()->create([
            'status' => 'paid',
            'status_date' => $paidAt->toDateString(),
            'payment_method' => 'card',
            'account_id' => null,
            'payment_order_number' => $paymeTransaction->payme_id,
            'author_id' => null,
        ]);

        $offer->update([
            'status' => 'paid',
            'status_date' => $paidAt->toDateString(),
            'payment_id' => $payment->id,
        ]);

        $paymeTransaction->payment_id = $payment->id;

        return $status;
    }

    public function notify(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        $this->dispatchPaid($offer, $status);
    }

    private function resolvePayment(CommercialOffer $offer, int $amountTiyin): Payment
    {
        $existing = $offer->payment_id
            ? Payment::query()->whereKey($offer->payment_id)->lockForUpdate()->first()
            : null;

        if ($existing && $existing->paid_at === null
            && PaymeMerchantService::toTiyin((string) $existing->sum) === $amountTiyin) {
            return $existing;
        }

        $offer->loadMissing('organization');
        $sum = PaymeMerchantService::tiyinToSum($amountTiyin);
        $payment = Payment::query()->create([
            'name' => trim((string) ($offer->client_name ?: $offer->organization?->name ?: 'Организация')),
            'phone' => preg_replace('/\D+/', '', (string) ($offer->client_phone ?: $offer->organization?->phone ?: '')) ?: '',
            'email' => trim((string) ($offer->client_email ?: $offer->organization?->email ?: '')),
            'sum' => $sum,
            'payment_type' => 'payme',
        ]);

        PaymentItem::query()->create([
            'payment_id' => $payment->id,
            'service_name' => 'Оплата подключения',
            'price' => $sum,
        ]);

        return $payment;
    }

    private function syncClientPartner(CommercialOffer $offer): void
    {
        if (!$offer->organization_id || !$offer->partner_id) {
            return;
        }

        $clientId = Organization::query()->whereKey($offer->organization_id)->value('client_id');
        if (!$clientId) {
            return;
        }

        Client::query()
            ->whereKey($clientId)
            ->where(function ($query) use ($offer) {
                $query->whereNull('partner_id')->orWhere('partner_id', '!=', $offer->partner_id);
            })
            ->update(['partner_id' => $offer->partner_id]);
    }

    private function dispatchPaid(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        $requestType = trim((string) ($offer->request_type ?: 'connection'));

        if ($requestType === 'connection_extra_services') {
            CommercialOfferExtraServicesPaidStatusEvent::dispatch($offer, $status);
        } elseif ($requestType === 'renewal') {
            CommercialOfferRenewalPaidStatusEvent::dispatch($offer, $status);
        } elseif ($requestType === 'renewal_no_changes') {
            CommercialOfferRenewalNoChangePaidStatusEvent::dispatch($offer, $status);
        } else {
            CommercialOfferPaidStatusEvent::dispatch($offer, $status);
        }
    }
}
