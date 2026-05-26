<?php

namespace App\Console\Commands;

use App\Jobs\ConnectionJob;
use App\Jobs\TariffExtensionJob;
use App\Models\IntegrationActionLog;
use App\Models\Organization;
use App\Services\Mailing\ResendMailService;
use Illuminate\Console\Command;

class ControlDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:control-demo-command';

    //
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private const DEMO_DAYS = 14;
    private const FOLLOW_UP_DAYS = [3, 6, 14, 18];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $organizations = Organization::query()
            ->whereHas('client', function ($query) {
                return $query->where('nfr', false);
            })
            ->whereDoesntHave('connections')
            ->with('client:id,name,email,is_demo,is_active,created_at')
            ->get();

        foreach ($organizations as $organization) {
            $daysSinceCreated = (int)$organization->created_at->diffInDays(now());

            if ($daysSinceCreated > self::DEMO_DAYS) {
                $organization->client?->update(['is_active' => false]);
                TariffExtensionJob::dispatch($organization, false);
            }
        }

    }

}
