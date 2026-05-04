<?php

namespace App\Listeners;

use App\Events\CommercialOfferRenewalNoChangePaidStatusEvent;
use App\Services\ClientBalances\ClientBalanceRegistryService;
use App\Services\ClientPaymentRegistries\ClientPaymentRegistryService;
use App\Services\CommercialOffers\CommercialOfferPaymentNotificationService;
use App\Services\DiscountExpenses\DiscountExpensesRegistryService;
use App\Services\ImplementationCurrencyRegistries\ImplementationCurrencyRegistryService;
use App\Services\OrganizationConnectionStatuses\OrganizationConnectionStatusRegistryService;
use App\Services\PartnerExpenses\PartnerExpensesRegistryService;

class CommercialOfferRenewalNoChangePaidStatusListener
{
    public function __construct(
        private DiscountExpensesRegistryService $discountExpensesRegistryService,
        private PartnerExpensesRegistryService $partnerExpensesRegistryService,
        private ClientPaymentRegistryService $clientPaymentRegistryService,
        private ClientBalanceRegistryService $clientBalanceRegistryService,
        private ImplementationCurrencyRegistryService $implementationCurrencyRegistryService,
        private OrganizationConnectionStatusRegistryService $organizationConnectionStatusRegistryService,
        private CommercialOfferPaymentNotificationService $paymentNotificationService
    ) {
    }

    public function handle(CommercialOfferRenewalNoChangePaidStatusEvent $event): void
    {
        $this->discountExpensesRegistryService->register($event->offer, $event->status);
        $this->partnerExpensesRegistryService->register($event->offer, $event->status);
        $this->clientPaymentRegistryService->register($event->offer, $event->status);
        $this->clientBalanceRegistryService->register($event->offer, $event->status);
        $this->implementationCurrencyRegistryService->register($event->offer, $event->status);
        $this->organizationConnectionStatusRegistryService->registerConnected($event->offer, $event->status);
        $this->paymentNotificationService->send($event->offer, $event->status);
    }
}
