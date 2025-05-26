<?php


namespace App\Services;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Transaction;
use App\Repositories\ClientRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WithdrawalService
{
    public function handle(Organization $organization, $sum)
    {
        $client = $organization->client()->first();
        if ($client->nfr) return true;
        if ($client->is_demo) return true;

        if ($organization->balance >= $sum) {
            $this->handleMissedPayments($client, $organization);

            $organization->balance -= $sum;
            $organization->save();

            $currency = $client->currency;

            $accountedAmount = $currency->symbol_code == 'USD' ? $sum / $currency->latestExchangeRate->kurs : $sum;

            Transaction::create([
                'client_id' => $client->id,
                'organization_id' => $organization->id,
                'tariff_id' => $client->tariff->id,
                'sale_id' => $client->sale?->id,
                'sum' => $sum,
                'type' => 'Снятие',
                'accounted_amount' => $accountedAmount
            ]);
        } else {
            $repository = new ClientRepository();
            $repository->activation($client, null);
        }
    }

    /**
     * Check and process missed payments when client adds funds
     */
    public function handleMissedPayments(Client $client, Organization $organization)
    {
        $lastPayment = Transaction::where([
            'client_id' => $client->id,
            'organization_id' => $organization->id,
            'type' => 'Снятие'
        ])->latest()->first();

        if (!$lastPayment || Carbon::parse($lastPayment->created_at)->isToday()) {
            return;
        }

        $lastPaymentDate = Carbon::parse($lastPayment->created_at)->startOfDay();
        $today = Carbon::today();
        $daysDifference = $lastPaymentDate->diffInDays($today);

        if ($daysDifference <= 1) {
            return;
        }

        $daysToCharge = $daysDifference - 1;
        $dailySum = $this->countSum($client);
        $totalMissedPayment = $dailySum * $daysToCharge;

        if ($organization->balance >= $totalMissedPayment) {
            DB::transaction(function () use ($client, $organization, $totalMissedPayment, $daysToCharge, $dailySum) {

                $organization->balance -= $totalMissedPayment;
                $organization->save();

                $currency = $client->currency;

                $accountedAmount = $currency->symbol_code == 'USD' ? $totalMissedPayment / $currency->latestExchangeRate->kurs : $totalMissedPayment;

                Transaction::create([
                    'client_id' => $client->id,
                    'organization_id' => $organization->id,
                    'tariff_id' => $client->tariff->id,
                    'sale_id' => $client->sale?->id,
                    'sum' => $totalMissedPayment,
                    'type' => 'Снятие (задолженность)',
                    'description' => "Оплата за пропущенные {$daysToCharge} дней по {$dailySum}",
                    'accounted_amount' => $accountedAmount
                ]);
            });
        }
    }

    public function countSum(Client $client)
    {
        $currentMonth = Carbon::now();

        $daysInMonth = $currentMonth->daysInMonth;

        $sum = $client->tariffPrice->tariff_price / $daysInMonth;

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
