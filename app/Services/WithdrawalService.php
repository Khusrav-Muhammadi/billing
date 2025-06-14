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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalService
{
    public function handle(Organization $organization, $sum)
    {
        $client = $organization->client()->first();

        if ($client->nfr || $client->is_demo) {
            return true;
        }

        $totalSum = $sum;
        $licensePrice = 0;

        if (!$organization->license_paid) {
            $licensePrice = $client->tariffPrice->license_price;
            $totalSum += $licensePrice;
        }

        if ($organization->balance < $totalSum) {
            $repository = new ClientRepository();
            $repository->activation($client, null);
            return false;
        }

        return DB::transaction(function () use ($organization, $client, $sum, $licensePrice) {
            $currency = $client->currency;

            $organization->balance -= $sum;
            $organization->save();

            $this->createTransaction(
                $client,
                $organization,
                $sum,
                $currency,
                TransactionPurpose::TARIFF
            );

            if (!$organization->license_paid && $licensePrice > 0) {
                $organization->decrement('balance', $licensePrice);

                $this->createTransaction(
                    $client,
                    $organization,
                    $licensePrice,
                    $currency,
                    TransactionPurpose::LICENSE
                );

                $organization->update(['license_paid' => true]);
            }

            return true;
        });
    }

    /**
     * Создает транзакцию с правильным расчетом accounted_amount
     */
    private function createTransaction(
        $client,
        $organization,
        $sum,
        $currency,
        $purpose
    ) {
        $accountedAmount = $this->calculateAccountedAmount($sum, $currency);

        Transaction::create([
            'client_id' => $client->id,
            'organization_id' => $organization->id,
            'tariff_id' => $client->tariffPrice->id,
            'sale_id' => $client->sale?->id,
            'sum' => $sum,
            'type' => TransactionType::DEBIT,
            'accounted_amount' => $accountedAmount,
            'purpose' => $purpose,
        ]);
    }

    /**
     * Рассчитывает сумму в учетной валюте
     */
    private function calculateAccountedAmount($sum, $currency)
    {
        if ($currency->symbol_code === 'USD') {
            return $sum;
        }

        $exchangeRate = $currency->latestExchangeRate->kurs ?? 1;
        return $sum / $exchangeRate;
    }

    public function countSum(Client $client)
    {
        $currentMonth = Carbon::now();
        $daysInMonth = $currentMonth->daysInMonth;

        $dailySum = $client->tariffPrice->tariff_price / $daysInMonth;

        $clientSale = $client->clientSales()
            ->whereHas('sale', function($query) {
                $query->where('apply_to', SaleApplies::PROGRESSIVE);
            })
            ->first();

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
