<?php

namespace App\Listeners;

use App\Events\CommercialOfferExtraServicesPaidStatusEvent;
use App\Services\ClientBalances\ClientBalanceRegistryService;
use App\Services\ClientPaymentRegistries\ClientPaymentRegistryService;
use App\Services\CommercialOffers\CommercialOfferProvisioningService;
use App\Services\ConnectedClientServices\ConnectedClientServicesRegistryService;
use App\Services\DiscountExpenses\DiscountExpensesRegistryService;
use App\Services\PartnerExpenses\PartnerExpensesRegistryService;

class CommercialOfferExtraServicesPaidStatusListener
{
    public function __construct(
        private ConnectedClientServicesRegistryService $connectedClientServicesRegistryService,
        private DiscountExpensesRegistryService $discountExpensesRegistryService,
        private PartnerExpensesRegistryService $partnerExpensesRegistryService,
        private ClientPaymentRegistryService $clientPaymentRegistryService,
        private ClientBalanceRegistryService $clientBalanceRegistryService,
        private CommercialOfferProvisioningService $commercialOfferProvisioningService
    ) {
    }

    public function handle(CommercialOfferExtraServicesPaidStatusEvent $event): void
    {
        $this->connectedClientServicesRegistryService->register($event->offer, $event->status);
        $this->discountExpensesRegistryService->register($event->offer, $event->status);
        $this->partnerExpensesRegistryService->register($event->offer, $event->status);
        $this->clientPaymentRegistryService->register($event->offer, $event->status);
        $this->clientBalanceRegistryService->register($event->offer, $event->status);
        $this->commercialOfferProvisioningService->provisionConnectionExtraServices($event->offer);
    }
}
