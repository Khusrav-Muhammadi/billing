<?php

namespace App\Console\Commands;

use App\Models\Client;
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
        $clients = Client::where([
            ['is_active', true],
            ['is_demo', true],
        ])->get();

        $service = new WithdrawalService();

        foreach ($clients as $client) {
            if ($client->created_at->diffInDays(Carbon::now()) > 14) {
                if ($client->balance == 0) {
                    $client->disableObserver = true;

                    $client->update([
                        'is_active' => false,
                        'is_demo' => false,
                    ]);

                } else {
                    $sum = $service->countSum($client);

                    $organizations = $client->organizations()
                        ->where('has_access', true)
                        ->get();

                    foreach ($organizations as $organization) {
                        $service->handle($organization, $sum);
                    }
                }
            }
        }

    }
}
