<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Базовые цены пакетов хранилища (услуги type=add_storage) в трёх валютах.
 *
 * USD — из прайса. UZS и TJS посчитаны по тем же курсам, что у остальных услуг
 * в таблице prices ($10 = 129 000 сум = 120 сомони), с округлением до «ровных» сумм.
 * Формат строки такой же, как у существующих услуг: kind=base, organization_id=NULL.
 */
return new class extends Migration
{
    private const CURRENCY_USD = 1;
    private const CURRENCY_UZS = 2;
    private const CURRENCY_TJS = 3;

    /** storage_gb => [USD, UZS, TJS] в месяц */
    private const PRICES = [
        10 => [1, 12900, 12],
        50 => [4, 51600, 48],
        100 => [7, 90300, 84],
        500 => [25, 322500, 300],
        1024 => [45, 580500, 540],
    ];

    public function up(): void
    {
        foreach (self::PRICES as $gb => [$usd, $uzs, $tjs]) {
            $tariffId = DB::table('tariffs')
                ->where('type', 'add_storage')
                ->where('storage_gb', $gb)
                ->value('id');

            if (!$tariffId) {
                continue;
            }

            $this->insertPrice($tariffId, self::CURRENCY_USD, $usd);
            $this->insertPrice($tariffId, self::CURRENCY_UZS, $uzs);
            $this->insertPrice($tariffId, self::CURRENCY_TJS, $tjs);
        }
    }

    public function down(): void
    {
        $ids = DB::table('tariffs')->where('type', 'add_storage')->pluck('id');

        DB::table('prices')
            ->whereIn('tariff_id', $ids)
            ->whereNull('organization_id')
            ->where('kind', 'base')
            ->delete();
    }

    /** Не дублируем, если базовая цена в этой валюте уже задана руками. */
    private function insertPrice(int $tariffId, int $currencyId, float $sum): void
    {
        $exists = DB::table('prices')
            ->where('tariff_id', $tariffId)
            ->where('currency_id', $currencyId)
            ->whereNull('organization_id')
            ->where('kind', 'base')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('prices')->insert([
            'tariff_id' => $tariffId,
            'organization_id' => null,
            'currency_id' => $currencyId,
            'kind' => 'base',
            'sum' => $sum,
            'start_date' => '2021-01-01',
            'date' => '9999-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
