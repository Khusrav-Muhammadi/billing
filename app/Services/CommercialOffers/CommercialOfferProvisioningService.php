<?php

namespace App\Services\CommercialOffers;

use App\Jobs\AddPackJob;
use App\Jobs\ConnectionJob;
use App\Jobs\UpdateTariffJob;
use App\Models\Client;
use App\Models\CommercialOffer;
use App\Models\Organization;
use App\Models\OrganizationConnectionStatus;
use App\Models\Tariff;
use RuntimeException;

class CommercialOfferProvisioningService
{
    public function provisionConnection(CommercialOffer $offer): void
    {
        $context = $this->resolveContext($offer);
        if (! $context) {
            throw new RuntimeException(
                "CommercialOfferProvisioningService: cannot provision connection for offer #{$offer->id} (organization/client/sub_domain missing)."
            );
        }

        $this->dispatchTariff($offer, $context['organization']);
        // Только паки этого КП (CRM add-pack — additive).
        $this->dispatchPackUpdates($offer, $context['organization']);
    }

    public function provisionConnectionExtraServices(CommercialOffer $offer): void
    {
        $context = $this->resolveContext($offer);
        if (! $context) {
            throw new RuntimeException(
                "CommercialOfferProvisioningService: cannot provision extra services for offer #{$offer->id} (organization/client/sub_domain missing)."
            );
        }

        // Только новые паки этого доп.КП — не пересылать уже выданные.
        $this->dispatchPackUpdates($offer, $context['organization']);
    }

    public function provisionRenewal(CommercialOffer $offer): void
    {
        $context = $this->resolveContext($offer);
        if (! $context) {
            throw new RuntimeException(
                "CommercialOfferProvisioningService: cannot provision renewal for offer #{$offer->id} (organization/client/sub_domain missing)."
            );
        }

        // Продление: update-tariff + только ПРИРОСТ паков (CRM add-pack additive).
        // Повторная отправка тех же воронок/юзеров/каналов удваивала бы их на CRM.
        $this->dispatchTariffUpdate($offer, $context['organization']);
        $this->dispatchPackIncreases($offer, $context['organization']);
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

        if (! $organization || ! $client || $subDomain === '') {
            return null;
        }

        return [
            'organization' => $organization,
            'client' => $client,
        ];
    }

    private function dispatchTariff(CommercialOffer $offer, Organization $organization): void
    {
        $organizationConnectionStatus = OrganizationConnectionStatus::where('commercial_offer_id', $offer->id)->first();
        if (! $organizationConnectionStatus) {
            return;
        }

        $tariffId = (int) ($offer->tariff_id ?? 0);
        if ($tariffId <= 0) {
            return;
        }

        $client = $organization->client;

        ConnectionJob::dispatchSync($organization, $tariffId, (string) $client->sub_domain);
    }

    private function dispatchTariffUpdate(CommercialOffer $offer, Organization $organization): void
    {
        $organizationConnectionStatus = OrganizationConnectionStatus::where('commercial_offer_id', $offer->id)->first();
        if (! $organizationConnectionStatus) {
            return;
        }

        $tariffId = (int) ($offer->tariff_id ?? 0);
        if ($tariffId <= 0) {
            return;
        }

        $client = $organization->client;

        UpdateTariffJob::dispatchSync($organization, $tariffId, (string) $client->sub_domain);
    }

    private function dispatchPackUpdates(CommercialOffer $offer, Organization $organization): void
    {
        $client = $organization->client;

        AddPackJob::dispatchSync(
            $organization,
            (string) $client->sub_domain,
            (int) $offer->id,
            false
        );
    }

    private function dispatchPackIncreases(CommercialOffer $offer, Organization $organization): void
    {
        $client = $organization->client;

        AddPackJob::dispatchSync(
            $organization,
            (string) $client->sub_domain,
            (int) $offer->id,
            true
        );
    }

    private function isPackLikeTariff(?Tariff $tariff): bool
    {
        if (! $tariff) {
            return false;
        }

        if ((bool) $tariff->is_extra_user) {
            return true;
        }

        return ! (bool) $tariff->is_tariff;
    }
}
