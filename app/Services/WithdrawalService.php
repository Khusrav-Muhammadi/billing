<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Transaction;
use App\Repositories\ClientRepository;
use App\Services\Billing\Enum\TransactionType;
use App\Services\Payment\Enums\TransactionPurpose;
use App\Services\Sale\Enum\SaleApplies;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

            $accountedAmount = $currency->symbol_code == 'USD' ? $sum : $sum / $currency->latestExchangeRate->kurs;

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

            if (!$organization->license_paid) {
                $accountedAmount = $currency->symbol_code == 'USD' ? $client->tariffPrice->license_price : $client->tariffPrice->license_price / $currency->latestExchangeRate->kurs;

                Transaction::create([
                    'client_id' => $client->id,
                    'organization_id' => $organization->id,
                    'tariff_id' => $client->tariffPrice->id,
                    'sale_id' => $client->sale?->id,
                    'sum' => $client->tariffPrice->license_price,
                    'type' => TransactionType::DEBIT,
                    'accounted_amount' => $accountedAmount,
                    'purpose' => TransactionPurpose::LICENSE,
                ]);

                $organization->update(['license_paid' => true]);
            }

        } else {
            $repository = new ClientRepository();
            $repository->activation($client, null);
        }
    }

    public function countSum(Client $client)
    {
        $currentMonth = Carbon::now();
        $daysInMonth = $currentMonth->daysInMonth;

        $dailySum = $client->tariffPrice->tariff_price / $daysInMonth;


        $clientSale = $client->clientSales()->whereHas('sale', function($query) {
            $query->where('apply_to', SaleApplies::PROGRESSIVE);
        })->first();

        if ($clientSale && $clientSale->sale->isActive()) {
            $sale = $clientSale->sale;

            if ($sale->sale_type === 'procent') {
                $discount = ($dailySum * $sale->amount) / 100;
                $dailySum -= $discount;
            } elseif ($sale->sale_type === 'fixed') {
                $dailyDiscount = $sale->amount / $daysInMonth;
                $dailySum -= $dailyDiscount;
            }

            $dailySum = max(0, $dailySum);
        }

        return $dailySum;
    }
}
