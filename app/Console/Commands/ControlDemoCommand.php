<?php

namespace App\Console\Commands;

use App\Jobs\TariffExtensionJob;
use App\Models\Client;
use App\Models\ConnectedClientServices;
use App\Models\Organization;
use App\Services\WithdrawalService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ControlDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:control-demo-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $organizations = Organization::query()
            ->whereDoesntHave('connections')
            ->get();

        foreach ($organizations as $organization) {
            if ($organization->created_at->diffInDays(now()) > 14) {
                $organization->client->update(['is_active' => false]);
                TariffExtensionJob::dispatch($organization, false);

            }
        }
    }
}
