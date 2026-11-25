<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Price;
use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    /**
     * Цена тарифа больше не лежит в tariffs.price: её пишет таблица prices.
     * Суммы ниже — месячная цена в USD, как было в старом сидере.
     */
    public function run(): void
    {
        $usdId = Currency::query()->where('symbol_code', 'USD')->value('id');

        $rows = [
            ['name' => 'Стандарт', 'user_count' => 3, 'project_count' => 1, 'usd' => '15.0000'],
            ['name' => 'Премиум', 'user_count' => 10, 'project_count' => 10, 'usd' => '39.0000'],
            ['name' => 'VIP', 'user_count' => 20, 'project_count' => 10000, 'usd' => '79.0000'],
        ];

        foreach ($rows as $row) {
            $tariff = Tariff::query()->firstOrCreate(
                ['name' => $row['name']],
                [
                    'user_count' => $row['user_count'],
                    'project_count' => $row['project_count'],
                    'is_tariff' => true,
                ]
            );

            if (!$usdId) {
                continue;
            }

            $exists = Price::query()
                ->where('tariff_id', $tariff->id)
                ->where('currency_id', $usdId)
                ->whereNull('organization_id')
                ->where('kind', 'base')
                ->exists();

            if ($exists) {
                continue;
            }

            Price::query()->create([
                'tariff_id' => $tariff->id,
                'organization_id' => null,
                'currency_id' => $usdId,
                'kind' => 'base',
                'sum' => $row['usd'],
                'start_date' => '2021-01-01',
                'date' => '9999-12-31',
            ]);
        }
    }
}
