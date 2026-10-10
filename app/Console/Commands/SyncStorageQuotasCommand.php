<?php

namespace App\Console\Commands;

use App\Services\Storage\StorageQuotaSyncService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Разослать в CRM лимиты файлового хранилища по всем организациям.
 * Ночной прогон ловит истёкшие пакеты и неудачные синки после оплаты.
 */
class SyncStorageQuotasCommand extends Command
{
    protected $signature = 'app:sync-storage-quotas
        {--organization= : Только одна организация (id)}
        {--queued : Класть задачи в очередь, не слать сразу}';

    protected $description = 'Отправить в CRM лимит файлового хранилища (тариф + доп. пакеты) по организациям.';

    public function handle(StorageQuotaSyncService $sync): int
    {
        $queued = (bool) $this->option('queued');

        $ids = $this->option('organization')
            ? [(int) $this->option('organization')]
            : $sync->organizationIdsToSync();

        $this->info('Organizations to sync: ' . count($ids));

        $ok = 0;
        $failed = 0;

        foreach ($ids as $organizationId) {
            try {
                $sync->syncOrganization($organizationId, sync: ! $queued);
                $ok++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("org #{$organizationId}: {$e->getMessage()}");
            }
        }

        $this->info("Done. ok={$ok} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
