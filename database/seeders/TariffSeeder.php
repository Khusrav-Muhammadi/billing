<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tariff::insert([
            ['name' => 'Стандарт', 'price' => 15, 'user_count' => 3, 'project_count' => 1],
            ['name' => 'Премиум', 'price' => 39, 'user_count' => 10, 'project_count' => 10],
            ['name' => 'VIP', 'price' => 79, 'user_count' => 20, 'project_count' => 10000],
        ]);
    }
}
