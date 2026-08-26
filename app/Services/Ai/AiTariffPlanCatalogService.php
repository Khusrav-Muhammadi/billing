<?php

namespace App\Services\Ai;

use App\Models\Ai\AiTariffPlan;
use Illuminate\Support\Collection;

class AiTariffPlanCatalogService
{
    /**
     * Active AI tariff plans for КП UI:
     * prices_by_currency + active periods (discounts).
     */
    public function forCommercialOffer(?string $asOfDate = null): Collection
    {
        $today = $asOfDate ?: now()->toDateString();

        return AiTariffPlan::query()
            ->where('is_active', true)
            ->with([
                'activePeriods',
                'aiModel',
                'prices' => function ($q) use ($today) {
                    $q->with('currency')
                        ->where('start_date', '<=', $today)
                        ->where(function ($qq) use ($today) {
                            $qq->whereNull('end_date')
                                ->orWhere('end_date', '9999-12-31')
                                ->orWhere('end_date', '>=', $today);
                        })
                        ->orderByDesc('start_date');
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                $pricesByCurrency = [];
                foreach ($p->prices as $priceRow) {
                    $code = $priceRow->currency?->symbol_code;
                    if (! $code) {
                        continue;
                    }
                    $code = strtoupper(trim($code));
                    if (! isset($pricesByCurrency[$code])) {
                        $pricesByCurrency[$code] = (float) $priceRow->price_monthly;
                    }
                }

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'category' => AiTariffPlan::normalizeCategory((string) ($p->category ?? '')),
                    'model_name' => $p->aiModel?->name ?? null,
                    'prices_by_currency' => $pricesByCurrency,
                    'periods' => $p->activePeriods->map(fn ($per) => [
                        'months' => (int) $per->months,
                        'discount_percent' => (float) $per->discount_percent,
                        'price_total' => (float) $per->price_total,
                    ])->values(),
                ];
            })
            ->filter(function (array $plan) {
                return ! empty($plan['prices_by_currency']) && $plan['periods']->count() > 0;
            })
            ->values();
    }
}
