<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Transaction;
use App\Repositories\ClientRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BalanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:balance-command';

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
        $clients = Client::where('is_active', true)->get();

        $currentMonth = Carbon::now();

        $daysInMonth = $currentMonth->daysInMonth;

        foreach ($clients as $client) {
            $sum = $client->tariff->price / $daysInMonth;

//            if ($client->sale_id) {
//                if ($client->sale->sale_type == 'procent') {
//                    $sum -= ($client->tariff->price*$client->sale->amount) / 100;
//                } else {
//                    $sum -= $client->sale->amount;
//                }
//            }

            $organizations = $client->organizations()
                ->where('has_access', true)
                ->get();

            foreach ($organizations as $organization) {
                if ($client->balance >= $sum) {
                    $client->balance -= $sum;
                    $client->disableObserver = true;
                    $client->save();

                    Transaction::create([
                        'client_id' => $client->id,
                        'organization_id' => $organization->id,
                        'tariff_id' => $client->tariff->id,
                        'sale_id' => $client->sale?->id,
                        'sum' => $sum,
                        'type' => 'Снятие',
                    ]);
                } else {
                    $repository = new ClientRepository();
                    $repository->activation($client);
                }
            }
        }
    }

}
