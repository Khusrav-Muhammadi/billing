<?php

namespace App\Listeners;

use App\Events\CommercialOfferPaidStatusEvent;
use App\Services\ClientBalances\ClientBalanceRegistryService;
use App\Services\ClientPaymentRegistries\ClientPaymentRegistryService;
use App\Services\CommercialOffers\CommercialOfferProvisioningService;
use App\Services\ConnectedClientServices\ConnectedClientServicesRegistryService;
use App\Services\DiscountExpenses\DiscountExpensesRegistryService;
use App\Services\OrganizationConnectionStatuses\OrganizationConnectionStatusRegistryService;
use App\Services\PartnerExpenses\PartnerExpensesRegistryService;

class



  цCommercialOfferPaidStatusListener
{
    public function __construct(
        private ConnectedClientServicesRegistryService $connectedClientServicesRegistryService,
        private DiscountExpensesRegistryService $discountExpensesRegistryService,
        private PartnerExpensesRegistryService $partnerExpensesRegistryService,
        private ClientPaymentRegistryService $clientPaymentRegistryService,
        private ClientBalanceRegistryService $clientBalanceRegistryService,
        private OrganizationConnectionStatusRegistryService $organizationConnectionStatusRegistryService,
        private CommercialOfferProvisioningService $commercialOfferProvisioningService
    ) {
    }

    public function handle(CommercialOfferPaidStatusEvent $event): void
    {
        $offer = $event->offer;

        $this->connectedClientServicesRegistryService->register($offer, $event->status);
        $this->discountExpensesRegistryService->register($offer, $event->status);
        $this->partnerExpensesRegistryService->register($offer, $event->status);
        $this->clientPaymentRegistryService->register($offer, $event->status);
        $this->clientBalanceRegistryService->register($offer, $event->status);
        $this->organizationConnectionStatusRegistryService->registerConnected($offer, $event->status);
        $this->commercialOfferProvisioningService->provisionConnection($offer);
    }
}
