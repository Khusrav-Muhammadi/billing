<?php


namespace App\Services;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Transaction;
use App\Repositories\ClientRepository;
use Carbon\Carbon;

class WithdrawalService
{
    public function handle(Organization $organization, $sum)
    {
        $client = $organization->client()->first();
        if ($client->nfr) return true;
        if ($client->is_demo) return true;
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
            $repository->activation($client, null);
        }
    }

    public function countSum(Client $client)
    {
        $currentMonth = Carbon::now();

        $daysInMonth = $currentMonth->daysInMonth;

        $sum = $client->tariff->price / $daysInMonth;

//            if ($client->sale_id) {
//                if ($client->sale->sale_type == 'procent') {
//                    $sum -= ($client->tariff->price*$client->sale->amount) / 100;
//                } else {
//                    $sum -= $client->sale->amount;
//                }
//            }
        return $sum;
    }
}
