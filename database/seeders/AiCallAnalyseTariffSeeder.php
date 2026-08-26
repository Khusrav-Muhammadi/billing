<?php

namespace Database\Seeders;

use App\Models\Ai\AiTariffPlan;
use App\Models\Ai\AiTariffPlanPeriod;
use App\Models\Ai\AiTariffPlanPrice;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class AiCallAnalyseTariffSeeder extends Seeder
{
    private const MONTHS = [1, 2, 3, 4, 5, 6];

    public function run(): void
    {
        $currencies = Currency::query()
            ->whereIn('symbol_code', ['USD', 'TJS', 'UZS'])
            ->get()
            ->keyBy(fn (Currency $currency) => strtoupper(trim((string) $currency->symbol_code)));

        foreach (['USD', 'TJS', 'UZS'] as $code) {
            if (! $currencies->has($code)) {
                throw new \RuntimeException("Currency [{$code}] is missing, cannot seed AI call-analyse tariffs.");
            }
        }

        AiTariffPlan::query()
            ->where('name', 'call')
            ->where('category', AiTariffPlan::CATEGORY_CALL_ANALYSE)
            ->update(['is_active' => false]);

        $plans = [
            ['name' => 'Start', 'usd' => 35],
            ['name' => 'Premium', 'usd' => 60],
            ['name' => 'Vip', 'usd' => 95],
        ];

        foreach ($plans as $planData) {
            $usd = (float) $planData['usd'];
            $plan = AiTariffPlan::query()->updateOrCreate(
                ['name' => $planData['name']],
                [
                    'category' => AiTariffPlan::CATEGORY_CALL_ANALYSE,
                    'is_active' => true,
                ]
            );

            $pricesByCode = [
                'USD' => $usd,
                'TJS' => $usd * 10,
                'UZS' => $usd * 12000,
            ];

            foreach ($pricesByCode as $code => $monthly) {
                $currencyId = (int) $currencies[$code]->id;
                AiTariffPlanPrice::query()->updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'currency_id' => $currencyId,
                        'start_date' => '2026-01-01',
                    ],
                    [
                        'price_monthly' => $monthly,
                        'end_date' => null,
                    ]
                );
            }

            foreach (self::MONTHS as $months) {
                AiTariffPlanPeriod::query()->updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'months' => $months,
                        'valid_to' => null,
                    ],
                    [
                        'discount_percent' => 0,
                        'price_total' => round($usd * $months, 2),
                        'valid_from' => '2026-01-01',
                    ]
                );
            }
        }
    }
}
