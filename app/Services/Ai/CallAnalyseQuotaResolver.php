<?php

namespace App\Services\Ai;

use App\Models\Ai\AiSubscription;
use App\Models\Ai\AiTariffPlan;
use Illuminate\Support\Carbon;

class CallAnalyseQuotaResolver
{
    /**
     * Активный лимит анализа звонков для организации.
     * Если тарифов несколько — берём больший дневной лимит.
     *
     * @return array{daily_minutes: int, plan_id: int|null, expires_at: string|null}
     */
    public function forOrganization(int $organizationId): array
    {
        $now = Carbon::now('Asia/Dushanbe');

        $rows = AiSubscription::query()
            ->where('organization_id', $organizationId)
            ->active()
            ->whereNotNull('expires_at')
            ->where('started_at', '<=', $now)
            ->where('expires_at', '>=', $now)
            ->with('plan')
            ->get()
            ->map(function (AiSubscription $subscription): ?array {
                $plan = $subscription->plan;
                if (! $plan) {
                    return null;
                }

                $category = AiTariffPlan::normalizeCategory((string) ($plan->category ?? ''));
                if ($category !== AiTariffPlan::CATEGORY_CALL_ANALYSE) {
                    return null;
                }

                return [
                    'plan_id' => (int) $plan->id,
                    'daily_minutes' => $plan->resolvedDailyMinutes(),
                    'expires_at' => $subscription->expires_at,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $best = self::pickBest($rows);
        if (! $best) {
            return [
                'daily_minutes' => 0,
                'plan_id' => null,
                'expires_at' => null,
            ];
        }

        $expiresAt = $best['expires_at'] instanceof Carbon
            ? $best['expires_at']->toIso8601String()
            : null;

        return [
            'daily_minutes' => (int) $best['daily_minutes'],
            'plan_id' => (int) $best['plan_id'],
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Выбираем тариф с большим дневным лимитом.
     * При равенстве — тот, что действует дольше.
     *
     * @param  list<array{plan_id:int, daily_minutes:int, expires_at:?Carbon}>  $rows
     * @return array{plan_id:int, daily_minutes:int, expires_at:?Carbon}|null
     */
    public static function pickBest(array $rows): ?array
    {
        $best = null;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $minutes = max(0, (int) ($row['daily_minutes'] ?? 0));
            if ($minutes <= 0) {
                continue;
            }

            if ($best === null) {
                $best = $row;
                $best['daily_minutes'] = $minutes;
                continue;
            }

            $bestMinutes = (int) $best['daily_minutes'];
            if ($minutes > $bestMinutes) {
                $best = $row;
                $best['daily_minutes'] = $minutes;
                continue;
            }

            if ($minutes < $bestMinutes) {
                continue;
            }

            $rowExpires = $row['expires_at'] ?? null;
            $bestExpires = $best['expires_at'] ?? null;
            $rowTs = $rowExpires instanceof Carbon ? $rowExpires->timestamp : 0;
            $bestTs = $bestExpires instanceof Carbon ? $bestExpires->timestamp : 0;

            if ($rowTs > $bestTs) {
                $best = $row;
                $best['daily_minutes'] = $minutes;
            }
        }

        return $best;
    }
}
