<?php

namespace Database\Seeders;

use App\Models\Pack;
use Illuminate\Database\Seeder;

class PackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pack::insert([
            ['name' => 'Пользователи', 'type' => 'user', 'amount' => 1, 'price' => 2.99, 'tariff_id' => 1],
            ['name' => 'Пользователи', 'type' => 'user', 'amount' => 1, 'price' => 2.59, 'tariff_id' => 2],
            ['name' => 'Пользователи', 'type' => 'user', 'amount' => 1, 'price' => 2.19, 'tariff_id' => 3],
        ]);
    }
}
