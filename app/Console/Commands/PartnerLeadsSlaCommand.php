<?php

namespace App\Console\Commands;

use App\Services\PartnerProgram\PartnerLeadService;
use Illuminate\Console\Command;

/** Тёплые лиды: проверить реакцию партнёров и передать просроченные (SLA 24 часа). */
class PartnerLeadsSlaCommand extends Command
{
    protected $signature = 'partners:leads-sla';

    protected $description = 'Тёплые лиды партнёров: закрепить принятые, передать просроченные следующему партнёру';

    public function handle(PartnerLeadService $leads): int
    {
        if (!PartnerLeadService::enabled()) {
            $this->info('Partner leads disabled (PARTNERS_LEADS_ENABLED=false).');

            return self::SUCCESS;
        }

        $stats = $leads->checkActive();
        $this->info(sprintf('accepted: %d, expired: %d, errors: %d', $stats['accepted'], $stats['expired'], $stats['errors']));

        return self::SUCCESS;
    }
}
