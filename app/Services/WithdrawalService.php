<?php


namespace App\Services;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Transaction;
use App\Repositories\ClientRepository;
use App\Services\Billing\Enum\TransactionType;
use App\Services\Payment\Enums\TransactionPurpose;
use Carbon\Carbon;

class WithdrawalService
{
    public function handle(Organization $organization, $sum)
    {
        $client = $organization->client()->first();

        if ($client->nfr) return true;
        if ($client->is_demo) return true;

        if ($organization->balance >= $sum) {
            $organization->balance -= $sum;
            $organization->save();

            $currency = $client->currency;

            $accountedAmount = $currency->symbol_code == 'USD' ? $sum : $sum / $currency->latestExchangeRate->kurs ;

            Transaction::create([
                'client_id' => $client->id,
                'organization_id' => $organization->id,
                'tariff_id' => $client->tariffPrice->id,
                'sale_id' => $client->sale?->id,
                'sum' => $sum,
                'type' => TransactionType::DEBIT,
                'accounted_amount' => $accountedAmount,
                'purpose' => TransactionPurpose::TARIFF,
            ]);
        } else {
            $repository = new ClientRepository();
            $repository->activation($client, null);
        }
    }

    /**
     * Check and process missed payments when client adds funds
     */
    public function countSum(Client $client)
    {
        $currentMonth = Carbon::now();

        $daysInMonth = $currentMonth->daysInMonth;

        $sum = $client->tariffPrice->tariff_price / $daysInMonth;

        return $sum;
    }
}
