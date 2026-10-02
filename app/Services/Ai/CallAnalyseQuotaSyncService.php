<?php

namespace App\Services\Ai;

use App\Jobs\Ai\SyncCallAnalyseQuotaJob;
use App\Models\Ai\AiSubscription;
use App\Models\Ai\AiTariffPlan;

class CallAnalyseQuotaSyncService
{
    public function __construct(
        private readonly CallAnalyseQuotaResolver $resolver,
    ) {
    }

    /**
     * Считать активный тариф анализа и отправить лимит в CRM.
     * Без подписки CRM получит 0 минут — разбор звонков остановится.
     */
    public function syncOrganization(int $organizationId, bool $sync = true): void
    {
        $quota = $this->resolver->forOrganization($organizationId);

        $job = new SyncCallAnalyseQuotaJob(
            organizationId: $organizationId,
            dailyMinutes: (int) $quota['daily_minutes'],
            planId: $quota['plan_id'],
            expiresAt: $quota['expires_at'],
        );

        if ($sync) {
            dispatch_sync($job);

            return;
        }

        dispatch($job);
    }

    /**
     * Все организации, у которых когда-либо был тариф анализа звонков.
     * Нужно, чтобы просроченным тоже отправить 0.
     *
     * @return list<int>
     */
    public function organizationIdsToSync(): array
    {
        return AiSubscription::query()
            ->whereHas('plan', function ($query): void {
                $query->where('category', AiTariffPlan::CATEGORY_CALL_ANALYSE);
            })
            ->distinct()
            ->orderBy('organization_id')
            ->pluck('organization_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
