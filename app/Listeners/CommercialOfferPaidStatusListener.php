<?php

namespace App\Listeners;

use App\Events\CommercialOfferPaidStatusEvent;
use App\Services\Ai\AiBalanceTopUpService;
use App\Services\ClientBalances\ClientBalanceRegistryService;
use App\Services\ClientPaymentRegistries\ClientPaymentRegistryService;
use App\Services\CommercialOffers\CommercialOfferPaymentNotificationService;
use App\Services\CommercialOffers\CommercialOfferProvisioningService;
use App\Services\ConnectedClientServices\ConnectedClientServicesRegistryService;
use App\Services\DiscountExpenses\DiscountExpensesRegistryService;
use App\Services\ImplementationCurrencyRegistries\ImplementationCurrencyRegistryService;
use App\Services\OrganizationConnectionStatuses\OrganizationConnectionStatusRegistryService;
use App\Services\Ai\AiSubscriptionRegistryService;
use App\Services\PartnerExpenses\PartnerExpensesRegistryService;

class CommercialOfferPaidStatusListener
{
    public function __construct(
        private ConnectedClientServicesRegistryService $connectedClientServicesRegistryService,
        private DiscountExpensesRegistryService $discountExpensesRegistryService,
        private PartnerExpensesRegistryService $partnerExpensesRegistryService,
        private ClientPaymentRegistryService $clientPaymentRegistryService,
        private ClientBalanceRegistryService $clientBalanceRegistryService,
        private ImplementationCurrencyRegistryService $implementationCurrencyRegistryService,
        private OrganizationConnectionStatusRegistryService $organizationConnectionStatusRegistryService,
        private CommercialOfferProvisioningService $commercialOfferProvisioningService,
        private CommercialOfferPaymentNotificationService $paymentNotificationService,
        private AiSubscriptionRegistryService $aiSubscriptionRegistryService,
        private AiBalanceTopUpService $aiBalanceTopUpService
    ) {
    }

    public function handle(CommercialOfferPaidStatusEvent $event): void
    {
        $offer = $event->offer;

        if (trim((string) ($offer->request_type ?: '')) === 'ai_topup') {
            $amount = (float) ($offer->payable_total ?: $offer->grand_total ?: 0);
            if ($amount > 0) {
                $this->aiBalanceTopUpService->topUp(
                    (int) $offer->organization_id,
                    $amount,
                    sprintf('Пополнение ИИ-счёта, платёж КП #%d', $offer->id)
                );
            }

            return;
        }

        $this->connectedClientServicesRegistryService->register($offer, $event->status);
        $this->discountExpensesRegistryService->register($offer, $event->status);
        $this->partnerExpensesRegistryService->register($offer, $event->status);
        $this->clientPaymentRegistryService->register($offer, $event->status);
        $this->clientBalanceRegistryService->register($offer, $event->status);
        $this->implementationCurrencyRegistryService->register($offer, $event->status);
        $this->organizationConnectionStatusRegistryService->registerConnected($offer, $event->status);
        $this->commercialOfferProvisioningService->provisionConnection($offer);
        $this->paymentNotificationService->send($offer, $event->status);
        $this->aiSubscriptionRegistryService->register($offer, $event->status);
    }
}
