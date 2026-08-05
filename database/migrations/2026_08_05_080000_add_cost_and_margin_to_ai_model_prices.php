<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ai_model_prices: себестоимость + маржа → авто продажная цена (price_per_1m_*).
 * Существующие price_* считаем себестоимостью с margin=0 (поведение не меняется).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_model_prices')) {
            return;
        }

        Schema::table('ai_model_prices', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_model_prices', 'cost_per_1m_input')) {
                $table->decimal('cost_per_1m_input', 20, 6)->default(0)->after('currency_id')
                    ->comment('Себестоимость входных токенов за 1M');
            }
            if (! Schema::hasColumn('ai_model_prices', 'cost_per_1m_output')) {
                $table->decimal('cost_per_1m_output', 20, 6)->default(0)->after('cost_per_1m_input')
                    ->comment('Себестоимость выходных токенов за 1M');
            }
            if (! Schema::hasColumn('ai_model_prices', 'margin_percent')) {
                $table->decimal('margin_percent', 5, 2)->default(0)->after('cost_per_1m_output')
                    ->comment('Маржа %: sell = cost / (1 - margin/100)');
            }
        });

        // Старые значения price_* были фактически «ручной продажей» —
        // переносим в cost и оставляем margin=0, чтобы sell = cost.
        DB::table('ai_model_prices')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $costIn = (float) ($row->cost_per_1m_input ?? 0);
                $costOut = (float) ($row->cost_per_1m_output ?? 0);

                if ($costIn <= 0 && $costOut <= 0) {
                    DB::table('ai_model_prices')->where('id', $row->id)->update([
                        'cost_per_1m_input' => $row->price_per_1m_input,
                        'cost_per_1m_output' => $row->price_per_1m_output,
                        'margin_percent' => 0,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_model_prices')) {
            return;
        }

        Schema::table('ai_model_prices', function (Blueprint $table): void {
            $cols = [];
            foreach (['cost_per_1m_input', 'cost_per_1m_output', 'margin_percent'] as $col) {
                if (Schema::hasColumn('ai_model_prices', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
