<?php

namespace App\Listeners;

use App\Events\CommercialOfferAiTopUpPaidStatusEvent;
use App\Services\Ai\AiBalanceTopUpService;

class CommercialOfferAiTopUpPaidStatusListener
{
    public function __construct(
        private readonly AiBalanceTopUpService $topUp,
    ) {
    }

    public function handle(CommercialOfferAiTopUpPaidStatusEvent $event): void
    {
        $offer = $event->offer;
        $amount = (float) ($offer->payable_total ?: $offer->grand_total ?: 0);
        if ($amount <= 0) {
            return;
        }

        $this->topUp->topUp(
            (int) $offer->organization_id,
            $amount,
            sprintf('Пополнение ИИ-счёта, платёж КП #%d', $offer->id)
        );
    }
}
