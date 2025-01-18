<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Transaction;
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
        $clients = Client::all();

        foreach ($clients as $client) {
            $sum = $client->tariff->price;

            if ($client->sale_id) {
                if ($client->sale->sale_type == 'procent') {
                    $sum -= ($client->tariff->price*$client->sale->amount) / 100;
                } else {
                    $sum -= $client->sale->amount;
                }
            }

            $client->balance -= $sum;
            $client->save();

            Transaction::create([
                'client_id' => $client->id,
                'tariff_id' => $client->tariff->id,
                'sale_id' => $client->sale?->id,
                'sum' => $sum,
                'type' => 'Снятие',
            ]);
        }

    }

}
