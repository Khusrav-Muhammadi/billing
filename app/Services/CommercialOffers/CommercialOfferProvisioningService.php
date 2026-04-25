<?php

namespace App\Services\CommercialOffers;

use App\Jobs\ActivationJob;
use App\Jobs\AddPackJob;
use App\Jobs\UpdateTariffJob;
use App\Models\Client;
use App\Models\CommercialOffer;
use App\Models\Organization;
use App\Models\OrganizationConnectionStatus;
use App\Models\OrganizationPack;
use App\Models\Pack;
use App\Models\Tariff;

class CommercialOfferProvisioningService
{
    public function provisionConnection(CommercialOffer $offer): void
    {
        $context = $this->resolveContext($offer);
        if (!$context) {
            return;
        }

        $this->dispatchActivation($context['organization'], $context['client']);
        $this->dispatchTariffUpdate($offer, $context['client']);
        $this->dispatchPackUpdates($offer, $context['organization'], $context['client']);
    }

    public function provisionConnectionExtraServices(CommercialOffer $offer): void
    {
        $context = $this->resolveContext($offer);
        if (!$context) {
            return;
        }

        $this->dispatchPackUpdates($offer, $context['organization'], $context['client']);
    }

    public function provisionRenewal(CommercialOffer $offer): void
    {
        $context = $this->resolveContext($offer);
        if (!$context) {
            return;
        }

        $this->dispatchTariffUpdate($offer, $context['client']);
        $this->dispatchPackUpdates($offer, $context['organization'], $context['client']);
    }

    /**
     * @return array{organization: Organization, client: Client}|null
     */
    private function resolveContext(CommercialOffer $offer): ?array
    {
        $offer->loadMissing([
            'organization:id,client_id',
            'organization.client:id,sub_domain',
            'items:id,commercial_offer_id,tariff_id,quantity',
            'items.tariff:id,is_tariff,is_extra_user',
        ]);

        $organization = $offer->organization;
        $client = $organization?->client;
        $subDomain = trim((string) ($client?->sub_domain ?? ''));

        if (!$organization || !$client || $subDomain === '') {
            return null;
        }

        return [
            'organization' => $organization,
            'client' => $client,
        ];
    }

    private function dispatchActivation(Organization $organization, Client $client): void
    {
        ActivationJob::dispatch(
            [(int) $organization->id],
            (string) $client->sub_domain,
            true,
            false
        );
    }

    private function dispatchTariffUpdate(CommercialOffer $offer, Client $client): void
    {
        $organizationConnectionStatus = OrganizationConnectionStatus::where('commercial_offer_id', $offer->id)->first();
        if (!$organizationConnectionStatus) return;
        $tariffId = (int) ($offer->tariff_id ?? 0);
        if ($tariffId <= 0) {
            return;
        }

        UpdateTariffJob::dispatch($client, $tariffId, (string) $client->sub_domain);
//        AddPackJob::dispatch();
    }

    private function dispatchPackUpdates(CommercialOffer $offer, Organization $organization, Client $client): void
    {
        $effectiveDate = $offer->status_date?->toDateString() ?: now()->toDateString();

        foreach ($offer->items as $item) {
            $tariff = $item->tariff;
            if (!$this->isPackLikeTariff($tariff)) {
                continue;
            }

            $pack = Pack::query()
                ->where('tariff_id', (int) $item->tariff_id)
                ->orderByDesc('id')
                ->first(['id']);

            if (!$pack) {
                continue;
            }

            $amount = max(1, (int) round((float) $item->quantity));

            $organizationPack = OrganizationPack::query()->firstOrCreate([
                'organization_id' => (int) $organization->id,
                'pack_id' => (int) $pack->id,
                'date' => $effectiveDate,
                'amount' => $amount,
            ]);

            if ($organizationPack->wasRecentlyCreated) {
                AddPackJob::dispatch($organizationPack, (string) $client->sub_domain);
            }
        }
    }

    private function isPackLikeTariff(?Tariff $tariff): bool
    {
        if (!$tariff) {
            return false;
        }

        if ((bool) $tariff->is_extra_user) {
            return true;
        }

        return !(bool) $tariff->is_tariff;
    }
}

