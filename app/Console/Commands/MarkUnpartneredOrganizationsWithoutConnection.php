<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationConnectionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkUnpartneredOrganizationsWithoutConnection extends Command
{
    protected $signature = 'app:mark-unpartnered-organizations-without-connection {--days=30 : Minimum organization age in days}';

    protected $description = 'Clear partner from clients of old organizations without connection status once.';

    public function handle(): int
    {
        $days = max(1, (int)$this->option('days'));
        $deadline = now()->subDays($days);
        $processed = 0;

        Organization::query()
            ->with('client:id,partner_id')
            ->where('created_at', '<=', $deadline)
            ->whereNull('partner_auto_cleared_at')
            ->whereDoesntHave('connections')
            ->whereHas('client', function ($query): void {
                $query->whereNotNull('partner_id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($organizations) use (&$processed): void {
                foreach ($organizations as $organization) {
                    DB::transaction(function () use ($organization, &$processed): void {
                        $lockedOrganization = Organization::query()
                            ->with('client:id,partner_id')
                            ->lockForUpdate()
                            ->find((int)$organization->id);

                        if (
                            !$lockedOrganization
                            || $lockedOrganization->partner_auto_cleared_at !== null
                            || !$lockedOrganization->client
                            || $lockedOrganization->client->partner_id === null
                        ) {
                            return;
                        }

                        $hasConnectionStatus = OrganizationConnectionStatus::query()
                            ->where('organization_id', (int)$lockedOrganization->id)
                            ->exists();

                        if ($hasConnectionStatus) {
                            return;
                        }

                        $lockedOrganization->client->update([
                            'partner_id' => null,
                        ]);

                        $lockedOrganization->forceFill([
                            'partner_auto_cleared_at' => now(),
                        ])->save();

                        $processed++;
                    });
                }
            });

        $this->info("Processed organizations: {$processed}");

        return self::SUCCESS;
    }
}
