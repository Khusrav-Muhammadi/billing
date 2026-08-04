<?php

namespace App\Observers;

use App\Models\Ai\AiTariffPlan;
use App\Models\Ai\AiTariffPlanPeriod;

class AiTariffPlanObserver
{
    /**
     * Recalculate price_total in all active periods when price_monthly changes.
     */
    public function updating(AiTariffPlan $plan): void
    {
        if (! $plan->isDirty('price_monthly')) {
            return;
        }

        $newMonthlyPrice = (float) $plan->price_monthly;

        AiTariffPlanPeriod::query()
            ->where('plan_id', $plan->id)
            ->whereNull('valid_to')
            ->each(function (AiTariffPlanPeriod $period) use ($newMonthlyPrice): void {
                $period->updateQuietly([
                    'price_total' => round(
                        $newMonthlyPrice * $period->months * (1 - (float) $period->discount_percent / 100),
                        4
                    ),
                ]);
            });
    }
}
