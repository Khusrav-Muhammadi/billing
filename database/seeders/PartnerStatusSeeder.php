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
            ['name' => 'Активный', 'is_active' => true],
            ['name' => 'Неактивный', 'is_active' => false],
        ]);
    }
}
