<?php

namespace Database\Seeders;

use App\Models\Pack;
use App\Models\Tariff;
use Illuminate\Database\Seeder;

class PackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prices = [
            'Стандарт' => '2.9900',
            'Премиум' => '2.5900',
            'VIP' => '2.1900',
        ];

        foreach ($prices as $tariffName => $price) {
            $tariffId = Tariff::query()->where('name', $tariffName)->value('id');
            if (!$tariffId) {
                continue;
            }

            Pack::query()->firstOrCreate(
                ['name' => 'Пользователи', 'tariff_id' => $tariffId],
                ['type' => 'user', 'amount' => 1, 'price' => $price]
            );
        }
    }
}
