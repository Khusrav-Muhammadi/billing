<?php

namespace App\Observers;

use App\Models\Ai\AiTariffPlan;

class AiTariffPlanObserver
{
    /**
     * price_monthly moved to ai_tariff_plan_prices.
     * Period price_total is recalculated in AiTariffController::pricesStore / periodsStore.
     */
    public function updating(AiTariffPlan $plan): void
    {
        // no-op
    }
}
