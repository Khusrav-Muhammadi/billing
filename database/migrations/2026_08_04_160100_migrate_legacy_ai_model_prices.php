<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Переносит цены из ai_models.cost_* и текущих ai_token_pricing в ai_model_prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_model_prices') || ! Schema::hasTable('ai_models')) {
            return;
        }

        $usdCurrencyId = DB::table('currencies')
            ->where(function ($q) {
                $q->whereRaw('UPPER(name) = ?', ['USD'])
                    ->orWhereRaw('UPPER(symbol_code) = ?', ['USD'])
                    ->orWhere('symbol_code', '$');
            })
            ->value('id');

        $models = DB::table('ai_models')->get();

        foreach ($models as $model) {
            $exists = DB::table('ai_model_prices')
                ->where('ai_model_id', $model->id)
                ->exists();
            if ($exists) {
                continue;
            }

            // 1) Текущая запись из ai_token_pricing по имени модели
            if (Schema::hasTable('ai_token_pricing')) {
                $legacy = DB::table('ai_token_pricing')
                    ->where('model_name', $model->name)
                    ->whereNull('effective_to')
                    ->where('is_active', true)
                    ->orderByDesc('effective_from')
                    ->first();

                if ($legacy) {
                    DB::table('ai_model_prices')->insert([
                        'ai_model_id'         => $model->id,
                        'currency_id'         => $legacy->price_currency_id,
                        'price_per_1m_input'  => $legacy->price_per_1m_input,
                        'price_per_1m_output' => $legacy->price_per_1m_output,
                        'start_date'          => $legacy->effective_from
                            ? date('Y-m-d', strtotime((string) $legacy->effective_from))
                            : now()->toDateString(),
                        'end_date'            => null,
                        'created_by'          => $legacy->created_by,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                    continue;
                }
            }

            // 2) Старые cost_* на самой модели
            $input = (float) ($model->cost_per_1m_input ?? 0);
            $output = (float) ($model->cost_per_1m_output ?? 0);
            if (($input > 0 || $output > 0) && $usdCurrencyId) {
                DB::table('ai_model_prices')->insert([
                    'ai_model_id'         => $model->id,
                    'currency_id'         => $usdCurrencyId,
                    'price_per_1m_input'  => $input,
                    'price_per_1m_output' => $output,
                    'start_date'          => now()->toDateString(),
                    'end_date'            => null,
                    'created_by'          => null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Данные не откатываем — могли быть добавлены вручную.
    }
};
