<?php

namespace App\Services\Storage;

use App\Jobs\Storage\SyncStorageQuotaJob;
use App\Models\Organization;

/**
 * Считает лимит хранилища через резолвер и отправляет его в CRM.
 * Вызывается после подключения / доп. услуг / продления и раз в сутки по расписанию.
 */
class StorageQuotaSyncService
{
    public function __construct(
        private readonly StorageQuotaResolver $resolver,
    ) {
    }

    public function syncOrganization(int $organizationId, bool $sync = true): void
    {
        $quota = $this->resolver->forOrganization($organizationId);

        $job = new SyncStorageQuotaJob(
            organizationId: $organizationId,
            storageLimitGb: (int) $quota['storage_limit_gb'],
        );

        if ($sync) {
            dispatch_sync($job);

            return;
        }

        dispatch($job);
    }

    /**
     * Организации с поддоменом CRM — им есть куда слать лимит.
     *
     * @return list<int>
     */
    public function organizationIdsToSync(): array
    {
        return Organization::query()
            ->whereHas('client', fn ($q) => $q->whereNotNull('sub_domain')->where('sub_domain', '!=', ''))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
