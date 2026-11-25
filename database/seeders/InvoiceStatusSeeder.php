<?php

namespace Database\Seeders;

use App\Models\InvoiceStatus;
use Illuminate\Database\Seeder;

class InvoiceStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Новый', 'is_new' => 1],
            ['name' => 'Успешный', 'is_success' => 1],
            ['name' => 'Не успешный', 'is_failed' => 1],
        ] as $row) {
            InvoiceStatus::query()->firstOrCreate(['name' => $row['name']], $row);
        }
    }
}
