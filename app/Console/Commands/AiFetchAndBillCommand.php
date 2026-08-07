<?php

namespace App\Console\Commands;

use App\Models\Ai\AiSubscription;
use App\Services\Ai\AiBillingService;
use App\Services\Ai\AiCrmFetchService;
use Illuminate\Console\Command;

class AiFetchAndBillCommand extends Command
{
    protected $signature = 'app:ai-fetch-and-bill';

    protected $description = 'Fetch AI usage logs from CRM and run 30-minute billing cycle.';

    public function handle(AiCrmFetchService $fetchService, AiBillingService $billingService): int
    {
        $this->info('Fetching AI usage logs from CRM...');
        $fetchService->fetchAll();

        $this->info('Running billing cycle...');

        $orgIds = AiSubscription::query()
            ->active()
            ->where('expires_at', '>=', now())
            ->distinct()
            ->pluck('organization_id');

        foreach ($orgIds as $orgId) {
            try {
                $billingService->processOrganization((int) $orgId);
            } catch (\Throwable $e) {
                $this->error("Billing failed for org #{$orgId}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
