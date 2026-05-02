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
            ->whereHas('client', function ($query) {
                return $query->where([
                    ['is_active', true],
                    ['is_demo', true],
                ]);
            })->get();

        foreach ($organizations as $organization) {
            if ($organization->created_at->diffInDays(Carbon::now()) > 14) {
                if (!ConnectedClientServices::query()->where('client_id', $organization->id)->exists()) {
                    TariffExtensionJob::dispatch($organization, false);
                }
            }
        }

    }
}
