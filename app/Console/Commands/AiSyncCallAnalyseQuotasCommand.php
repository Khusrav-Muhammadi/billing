<?php

namespace App\Console\Commands;

use App\Services\Ai\CallAnalyseQuotaSyncService;
use Illuminate\Console\Command;
use Throwable;

class AiSyncCallAnalyseQuotasCommand extends Command
{
    protected $signature = 'app:ai-sync-call-analyse-quotas {--queued : Класть задачи в очередь, не слать сразу}';

    protected $description = 'Отправить в CRM дневной лимит минут анализа звонков по оплаченным тарифам.';

    public function handle(CallAnalyseQuotaSyncService $sync): int
    {
        $queued = (bool) $this->option('queued');
        $ids = $sync->organizationIdsToSync();

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
