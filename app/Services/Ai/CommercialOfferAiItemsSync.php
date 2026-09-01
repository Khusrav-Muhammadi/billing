<?php

namespace App\Services\Ai;

use App\Models\Ai\AiTariffPlan;
use App\Models\Ai\CommercialOfferAiItem;
use App\Models\CommercialOffer;
use App\Models\Organization;

class CommercialOfferAiItemsSync
{
    public function sync(CommercialOffer $offer, array $payload, ?Organization $organization, bool $isBaseTariff): void
    {
        $rows = data_get($payload, 'ai_items');
        if (! is_array($rows) || $rows === []) {
            $single = data_get($payload, 'ai_item');
            $rows = is_array($single) && ! empty($single['plan_id']) ? [$single] : [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['plan_id'])) {
                continue;
            }

            $plan = AiTariffPlan::query()->find((int) $row['plan_id']);
            if (! $plan) {
                continue;
            }

            $category = $this->normalizeCategory((string) ($row['category'] ?? $plan->category ?? 'chat'));
            $isCallAnalyse = $category === 'call_analyse';
            $unitPrice = max(0, (float) ($row['unit_price'] ?? 0));
            $requestedDemoDays = (int) ($row['demo_days'] ?? 0);
            $demoDays = (
                ! $isCallAnalyse
                && CommercialOfferAiItem::allowsDemoForRequestType((string) $offer->request_type)
                && $requestedDemoDays === CommercialOfferAiItem::DEMO_DAYS
            ) ? CommercialOfferAiItem::DEMO_DAYS : 0;

            if ($demoDays > 0) {
                $periodMonths = 0;
                $giftMonths = 0;
                $discountPercent = 0.0;
                $demoAmount = CommercialOfferAiItem::demoAmount($unitPrice, $demoDays);
                $originalPrice = $demoAmount;
                $totalPrice = $demoAmount;
                $currentMonthAmount = 0.0;
            } else {
                $periodMonths = max(0, (int) ($row['period_months'] ?? 0));
                $giftMonths = $isCallAnalyse
                    ? 0
                    : CommercialOfferAiItem::resolveGiftMonths($periodMonths, $organization, $isBaseTariff);
                $discountPercent = $isCallAnalyse ? 0.0 : max(0, (float) ($row['discount_percent'] ?? 0));
                $originalPrice = $periodMonths > 0 ? round($unitPrice * $periodMonths, 4) : 0.0;
                $totalPrice = $isCallAnalyse
                    ? $originalPrice
                    : round($originalPrice * (1 - $discountPercent / 100), 4);
                $currentMonthAmount = max(0, (float) ($row['current_month_amount'] ?? 0));
            }

            $normalized[] = [
                'commercial_offer_id' => $offer->id,
                'plan_id' => (int) $plan->id,
                'period_months' => $periodMonths,
                'demo_days' => $demoDays,
                'gift_months' => $giftMonths,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'partner_percent' => max(0, (float) ($row['partner_percent'] ?? 0)),
                'original_price' => $originalPrice > 0 ? $originalPrice : max(0, (float) ($row['original_price'] ?? 0)),
                'total_price' => $totalPrice > 0 ? $totalPrice : max(0, (float) ($row['total_price'] ?? 0)),
                'current_month_amount' => $currentMonthAmount,
                'balance_topup' => $isCallAnalyse ? 0 : max(0, (float) ($row['balance_topup'] ?? 0)),
            ];
        }

        CommercialOfferAiItem::query()
            ->where('commercial_offer_id', $offer->id)
            ->delete();

        foreach ($normalized as $data) {
            CommercialOfferAiItem::query()->create($data);
        }
    }

    public function toPayloadRows(CommercialOffer $offer): array
    {
        $offer->loadMissing(['aiItems.plan:id,name,category']);

        $order = [AiTariffPlan::CATEGORY_CHAT => 0, AiTariffPlan::CATEGORY_CALL_ANALYSE => 1];

        return $offer->aiItems
            ->map(function (CommercialOfferAiItem $item): array {
                $category = $this->normalizeCategory((string) ($item->plan?->category ?? 'chat'));

                return [
                    'plan_id' => (int) $item->plan_id,
                    'plan_name' => (string) ($item->plan?->name ?? ''),
                    'category' => $category,
                    'period_months' => (int) $item->period_months,
                    'demo_days' => (int) ($item->demo_days ?? 0),
                    'gift_months' => (int) ($item->gift_months ?? 0),
                    'unit_price' => (float) $item->unit_price,
                    'discount_percent' => (float) $item->discount_percent,
                    'partner_percent' => (float) $item->partner_percent,
                    'original_price' => (float) $item->original_price,
                    'total_price' => (float) $item->total_price,
                    'current_month_amount' => (float) ($item->current_month_amount ?? 0),
                    'balance_topup' => (float) ($item->balance_topup ?? 0),
                    'currency' => '',
                ];
            })
            ->sortBy(fn (array $row) => $order[$row['category']] ?? 99)
            ->values()
            ->all();
    }

    public function normalizeCategory(string $value): string
    {
        return AiTariffPlan::normalizeCategory($value);
    }
}
