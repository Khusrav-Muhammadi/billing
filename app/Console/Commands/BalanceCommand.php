<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Transaction;
use App\Repositories\ClientRepository;
use App\Services\WithdrawalService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BalanceCommand extends Command
{

    protected $signature = 'app:balance-command';

    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clients = Client::where([
            ['is_active', true],
            ['nfr', false]
        ])->get();

        $service = new WithdrawalService();

        foreach ($clients as $client) {

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
