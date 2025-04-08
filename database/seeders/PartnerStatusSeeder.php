<?php

namespace Database\Seeders;

use App\Models\PartnerStatus;
use Illuminate\Database\Seeder;

class PartnerStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PartnerStatus::insert([
            ['name' => 'Активный', 'is_active' => true, 'connect_amount' => 1, 'organization_connect_percent' => 1, 'tariff_price_percent' => 1],
            ['name' => 'Неактивный', 'is_active' => false, 'connect_amount' => 1, 'organization_connect_percent' => 1, 'tariff_price_percent' => 1],
        ]);
    }
}
