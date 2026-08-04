<?php

namespace App\Observers;

use App\Models\Ai\AiTokenPricing;

class AiTokenPricingObserver
{
    /**
     * Auto-calculate price fields from cost + margin before insert.
     */
    public function creating(AiTokenPricing $pricing): void
    {
        if ((float) $pricing->margin_percent >= 100) {
            throw new \InvalidArgumentException('margin_percent must be less than 100.');
        }

        $divisor = 1 - ((float) $pricing->margin_percent / 100);

        $pricing->price_per_1m_input = round((float) $pricing->cost_per_1m_input / $divisor, 6);
        $pricing->price_per_1m_output = round((float) $pricing->cost_per_1m_output / $divisor, 6);
    }

    /**
     * Forbid direct update of pricing fields.
     * Use AiTokenPricing::updatePrice() for SCD Type 2 versioning.
     */
    public function updating(AiTokenPricing $pricing): void
    {
        $pricingFields = [
            'cost_per_1m_input',
            'cost_per_1m_output',
            'margin_percent',
            'price_per_1m_input',
            'price_per_1m_output',
            'cost_currency_id',
            'price_currency_id',
        ];

        $dirty = array_keys($pricing->getDirty());

        foreach ($pricingFields as $field) {
            if (in_array($field, $dirty, true)) {
                throw new \LogicException(
                    "Direct update of pricing field [{$field}] is forbidden. Use AiTokenPricing::updatePrice()."
                );
            }
        }
    }
}
