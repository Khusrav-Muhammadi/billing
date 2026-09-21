<?php

namespace App\Services\Payment;

use App\Models\Ai\CommercialOfferAiItem;
use App\Models\CommercialOffer;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Подставляет в строки счёта количество, месяцы и цену за единицу.
 * CRM: commercial_offer_items. ИИ: commercial_offer_ai_items.
 * Текущий месяц ИИ = 1. Период ИИ = period_months.
 */
class InvoicePaymentItemPresenter
{
    public function enrich(Payment $payment, ?CommercialOffer $offer): Collection
    {
        $payment->loadMissing('paymentItems');
        $offer?->loadMissing(['items.tariff:id,name', 'aiItems.plan:id,name,category']);

        $crmItems = $offer?->items?->values() ?? collect();
        $aiLines = $this->buildAiVirtualLines($offer?->aiItems ?? collect());
        $usedCrm = [];
        $usedAi = [];

        foreach ($payment->paymentItems as $item) {
            $matched = $this->matchAiLine($item, $aiLines, $usedAi)
                ?? $this->matchCrmItem($item, $crmItems, $usedCrm);

            $name = (string) $item->service_name;
            $sum = (float) $item->price;
            $quantity = $matched['quantity'] ?? 1;
            $months = $matched['months'] ?? $this->fallbackMonths($name);
            $unitPrice = $matched['unit_price'] ?? null;
            if ($unitPrice === null || $unitPrice <= 0) {
                $unitPrice = $quantity > 0 && (int) $months > 0
                    ? round($sum / $quantity / (int) $months, 4)
                    : ($quantity > 0 ? round($sum / $quantity, 4) : $sum);
            }

            $item->setAttribute('quantity', $quantity);
            $item->setAttribute('months', $months);
            $item->setAttribute('period_months', $months);
            $item->setAttribute('unit_price', $unitPrice);
        }

        return $payment->paymentItems;
    }

    public function toApiItems(Payment $payment, ?CommercialOffer $offer): Collection
    {
        return $this->enrich($payment, $offer)->map(function ($item) {
            $months = $item->getAttribute('months');

            return [
                'service_name' => $item->service_name,
                'price' => $item->price,
                'quantity' => $item->getAttribute('quantity') ?? 1,
                'months' => $months,
                'period_months' => $item->getAttribute('period_months') ?? $months,
                'unit_price' => $item->getAttribute('unit_price'),
            ];
        })->values();
    }

    private function buildAiVirtualLines(Collection $aiItems): array
    {
        $lines = [];
        foreach ($aiItems as $aiItem) {
            if (! $aiItem instanceof CommercialOfferAiItem) {
                continue;
            }
            $planName = (string) ($aiItem->plan?->name ?? '');
            $demoDays = max(0, (int) ($aiItem->demo_days ?? 0));
            $periodMonths = max(0, (int) ($aiItem->period_months ?? 0));
            $unitPrice = (float) ($aiItem->unit_price ?? 0);
            $totalPrice = (float) ($aiItem->total_price ?? 0);
            $currentMonth = (float) ($aiItem->current_month_amount ?? 0);
            $balanceTopup = (float) ($aiItem->balance_topup ?? 0);

            if ($demoDays > 0 && $totalPrice > 0) {
                $lines[] = $this->aiLine('demo', $planName, 0, $totalPrice, $totalPrice);
                continue;
            }
            if ($currentMonth > 0) {
                $lines[] = $this->aiLine('current_month', $planName, 1, $currentMonth, $currentMonth);
            }
            if ($totalPrice > 0 && $periodMonths > 0) {
                $lines[] = $this->aiLine('plan', $planName, $periodMonths, $unitPrice, $totalPrice);
            }
            if ($balanceTopup > 0) {
                $lines[] = $this->aiLine('balance', $planName, 0, $balanceTopup, $balanceTopup);
            }
        }

        return $lines;
    }

    private function aiLine(string $kind, string $planName, int $months, float $unitPrice, float $totalPrice): array
    {
        return [
            'kind' => $kind,
            'plan_name' => $planName,
            'quantity' => 1,
            'months' => $months,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
        ];
    }

    private function matchAiLine($paymentItem, array $lines, array &$used): ?array
    {
        $name = (string) $paymentItem->service_name;
        if (! $this->looksLikeAi($name)) {
            return null;
        }

        $kind = $this->aiKindFromName($name);
        $price = (float) $paymentItem->price;
        $normalizedName = $this->normalizeName($name);

        foreach (['kind_price', 'price', 'kind_plan'] as $mode) {
            foreach ($lines as $index => $line) {
                if (isset($used[$index])) {
                    continue;
                }
                $kindOk = $kind && $line['kind'] === $kind;
                $priceOk = $this->amountsEqual((float) $line['total_price'], $price);
                $plan = $this->normalizeName((string) $line['plan_name']);
                $planOk = $plan !== '' && str_contains($normalizedName, $plan);
                $hit = match ($mode) {
                    'kind_price' => $kindOk && $priceOk,
                    'price' => $priceOk,
                    default => $kindOk && $planOk,
                };
                if ($hit) {
                    $used[$index] = true;

                    return $line;
                }
            }
        }

        return null;
    }

    private function matchCrmItem($paymentItem, Collection $offerItems, array &$used): ?array
    {
        $paymentName = $this->normalizeName((string) $paymentItem->service_name);
        $paymentPrice = (float) $paymentItem->price;

        foreach (['name_price', 'name', 'price'] as $mode) {
            foreach ($offerItems as $index => $offerItem) {
                if (isset($used[$index])) {
                    continue;
                }
                $itemName = $this->normalizeName((string) ($offerItem->tariff?->name ?? ''));
                $nameOk = $itemName !== '' && $itemName === $paymentName;
                $priceOk = $this->amountsEqual((float) $offerItem->total_price, $paymentPrice);
                $hit = match ($mode) {
                    'name_price' => $nameOk && $priceOk,
                    'name' => $nameOk,
                    default => $priceOk,
                };
                if (! $hit) {
                    continue;
                }
                $used[$index] = true;
                $quantity = (float) $offerItem->quantity;
                $quantity = floor($quantity) === $quantity ? (int) $quantity : $quantity;

                return [
                    'quantity' => $quantity > 0 ? $quantity : 1,
                    'months' => (int) $offerItem->months,
                    'unit_price' => (float) $offerItem->unit_price,
                ];
            }
        }

        return null;
    }

    private function fallbackMonths(string $name): ?int
    {
        return $this->looksLikeAi($name) ? $this->monthsFromServiceName($name) : null;
    }

    private function monthsFromServiceName(string $name): int
    {
        $normalized = mb_strtolower($name);
        if (str_contains($normalized, 'текущий месяц')) {
            return 1;
        }
        if (str_contains($normalized, 'демо') || str_contains($normalized, 'баланс')) {
            return 0;
        }
        if (preg_match('/\(\+?(\d+)\s*мес/u', $name, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    private function aiKindFromName(string $name): ?string
    {
        $normalized = mb_strtolower($name);
        if (str_contains($normalized, 'текущий месяц')) {
            return 'current_month';
        }
        if (str_contains($normalized, 'демо')) {
            return 'demo';
        }
        if (str_contains($normalized, 'баланс')) {
            return 'balance';
        }
        if (str_contains($normalized, 'подарок')) {
            return 'gift';
        }
        if (preg_match('/\(\+?\d+\s*мес/u', $normalized) || $this->looksLikeAi($name)) {
            return 'plan';
        }

        return null;
    }

    private function looksLikeAi(string $name): bool
    {
        $normalized = mb_strtolower($name);

        return str_contains($normalized, 'ии-агент')
            || str_contains($normalized, 'ии агент')
            || str_contains($normalized, 'баланс ии')
            || str_contains($normalized, 'пополнение ии');
    }

    private function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($name)));
    }

    private function amountsEqual(float $left, float $right): bool
    {
        return abs($left - $right) < 0.01;
    }
}
