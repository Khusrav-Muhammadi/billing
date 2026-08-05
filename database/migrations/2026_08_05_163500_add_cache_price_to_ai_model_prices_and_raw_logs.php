<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Цена cache-токенов /1M (себестоимость + продажа).
 * Формула биллинга:
 *   (prompt - cache) * price_input + cache * price_cache + completion * price_output
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_model_prices')) {
            Schema::table('ai_model_prices', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_model_prices', 'cost_per_1m_cache')) {
                    $table->decimal('cost_per_1m_cache', 20, 6)
                        ->default(0)
                        ->after('cost_per_1m_output')
                        ->comment('Себестоимость cache-токенов за 1M');
                }
                if (! Schema::hasColumn('ai_model_prices', 'price_per_1m_cache')) {
                    $table->decimal('price_per_1m_cache', 20, 6)
                        ->default(0)
                        ->after('price_per_1m_output')
                        ->comment('Продажа cache-токенов за 1M');
                }
            });

            $deepseekProIds = DB::table('ai_models')
                ->where('name', 'deepseek-v4-pro')
                ->pluck('id')
                ->all();

            // Backfill:
            // - deepseek-v4-pro: ~0.09 при input 0.435 (как в CRM)
            // - остальные: 10% от входной себестоимости (типичный DeepSeek cache hit)
            DB::table('ai_model_prices')->orderBy('id')->chunkById(200, function ($rows) use ($deepseekProIds): void {
                foreach ($rows as $row) {
                    $margin = (float) $row->margin_percent;
                    $divisor = 1 - ($margin / 100);
                    if ($divisor <= 0) {
                        continue;
                    }

                    $costIn = (float) $row->cost_per_1m_input;
                    $ratio = in_array((int) $row->ai_model_id, $deepseekProIds, true)
                        ? (0.09 / 0.435)
                        : 0.1;

                    $costCache = round($costIn * $ratio, 6);
                    $priceCache = round($costCache / $divisor, 6);

                    DB::table('ai_model_prices')->where('id', $row->id)->update([
                        'cost_per_1m_cache' => $costCache,
                        'price_per_1m_cache' => $priceCache,
                    ]);
                }
            });
        }

        if (Schema::hasTable('ai_usage_raw_logs')) {
            Schema::table('ai_usage_raw_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_usage_raw_logs', 'price_per_1m_cache_snapshot')) {
                    $table->decimal('price_per_1m_cache_snapshot', 20, 6)
                        ->nullable()
                        ->after('price_per_1m_output_snapshot');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_usage_raw_logs') && Schema::hasColumn('ai_usage_raw_logs', 'price_per_1m_cache_snapshot')) {
            Schema::table('ai_usage_raw_logs', function (Blueprint $table): void {
                $table->dropColumn('price_per_1m_cache_snapshot');
            });
        }

        if (Schema::hasTable('ai_model_prices')) {
            Schema::table('ai_model_prices', function (Blueprint $table): void {
                if (Schema::hasColumn('ai_model_prices', 'price_per_1m_cache')) {
                    $table->dropColumn('price_per_1m_cache');
                }
                if (Schema::hasColumn('ai_model_prices', 'cost_per_1m_cache')) {
                    $table->dropColumn('cost_per_1m_cache');
                }
            });
        }
    }
};
