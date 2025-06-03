<?php

namespace App\Services\Sale;

use App\Models\Sale;
use App\Services\Sale\Enum\SaleApplies;
use Illuminate\Support\Collection;

class SaleService
{
    public function getActiveSales(): Collection
    {
        return Sale::where('is_active', true)
            ->where(function($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->get();
    }

    public function applyDiscounts(Collection $sales, array $operationData, array &$metadata): array
    {
        $discounts = [];
        $metadata['discounts'] = []; // Для хранения информации о скидках

        foreach ($sales as $sale) {
            if (!$this->isSaleApplicable($sale, $operationData)) {
                continue;
            }

            if ($sale->apply_to === SaleApplies::PROGRESSIVE->value) {

                $metadata['discounts']['tariff'] = [
                    'percent' => $sale->amount,
                    'months_required' => $sale->min_months,
                    'sale_id' => $sale->id
                ];
                $metadata['applied_discounts'][] = $sale->name;

                continue;
            }
            if ($sale->apply_to === SaleApplies::LICENSE->value) {
                $metadata['discounts']['license'] = [
                    'percent' => $sale->amount,
                    'type' => $sale->sale_type,
                    'sale_id' => $sale->id
                ];
                $metadata['applied_discounts'][] = $sale->name;
                continue;
            }
            $discountAmount = $this->calculateDiscountForSale($sale, $metadata);
            if ($discountAmount > 0) {
                $discounts[] = $discountAmount;
                $metadata['applied_discounts'][] = $sale->name;
            }
        }
        return $discounts;
    }

    private function isSaleApplicable(Sale $sale, array $operationData): bool
    {
        if (!$sale->is_active) {
            return false;
        }

        // Проверка дат активности скидки
        $now = now();
        if ($sale->start_date && $now < $sale->start_date) {
            return false;
        }
        if ($sale->end_date && $now > $sale->end_date) {
            return false;
        }

        // Для прогрессивных скидок проверяем минимальное количество месяцев
        if ($sale->apply_to === SaleApplies::PROGRESSIVE->value) {
            $months = $operationData['months'] ?? 1;
            return $months >= $sale->min_months;
        }

        return true;
    }

    private function calculateDiscountForSale(Sale $sale, array $metadata): float
    {
        // Эта логика теперь не используется для прогрессивных скидок и скидок на лицензию
        $amount = match ($sale->apply_to) {
            SaleApplies::TARIFF->value => $metadata['tariff_price'] ?? 0,
            default => 0
        };

        return $sale->sale_type === 'procent'
            ? ($amount * $sale->amount) / 100
            : min($amount, $sale->amount);
    }
}
