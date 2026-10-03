<?php

namespace Database\Seeders;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelPrice;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Актуальные себестоимости за 1 млн токенов на 3 октября 2026.
 * DeepSeek записан по пику: будни, Пекин 9:00–12:00 и 14:00–18:00.
 * Непик ровно вдвое дешевле, в таблице одна цена.
 * Продажа считается в модели: sell = cost / (1 - margin/100).
 * TJS = USD x10, UZS = USD x12000 — как в уже лежащих прайсах.
 */
class AiModelPriceSeeder extends Seeder
{
    private const START_DATE = '2026-09-10';

    private const FX = [
        'USD' => 1,
        'TJS' => 10,
        'UZS' => 12000,
    ];

    public function run(): void
    {
        $currencies = Currency::query()
            ->whereIn('symbol_code', array_keys(self::FX))
            ->get()
            ->keyBy(fn (Currency $currency) => strtoupper((string) $currency->symbol_code));

        foreach (array_keys(self::FX) as $code) {
            if (! $currencies->has($code)) {
                throw new \RuntimeException("Currency [{$code}] is missing.");
            }
        }

        $createdBy = User::query()->orderBy('id')->value('id');

        DB::transaction(function () use ($currencies, $createdBy): void {
            $this->reprice($currencies, $createdBy, 'deepseek-v4-pro', 'deepseek', 90, [
                'input' => 1.32,
                'cache' => 0.044,
                'output' => 3.96,
            ]);

            // Старое имя снято 10 сентября: запросы идут в V4.1-Flash и считаются по пиковой цене Flash.
            $this->reprice($currencies, $createdBy, 'deepseek-v4-flash', 'deepseek', 90, [
                'input' => 0.30,
                'cache' => 0.006,
                'output' => 1.20,
            ]);

            $this->reprice($currencies, $createdBy, 'deepseek-flash', 'deepseek', 90, [
                'input' => 0.30,
                'cache' => 0.006,
                'output' => 1.20,
            ]);

            // До 31.12.2026 у Gemini вводный тариф. 1.50/7.50 — это цена с 01.01.2027.
            foreach (['gemini-3.6-flash', 'gemini-3.7-flash'] as $name) {
                $this->reprice($currencies, $createdBy, $name, 'gemini', null, [
                    'input' => 0.75,
                    'cache' => 0.075,
                    'output' => 3.75,
                ], createMissing: false);
            }
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Currency>  $currencies
     * @param  array{input: float, cache: float, output: float}  $usd
     */
    private function reprice($currencies, mixed $createdBy, string $name, string $provider, ?float $defaultMargin, array $usd, bool $createMissing = true): void
    {
        $model = AiModel::query()->where('name', $name)->first();
        if (! $model && ! $createMissing) {
            $this->command?->warn("Model [{$name}] is not in billing, price not changed.");

            return;
        }

        if (! $model) {
            $model = AiModel::query()->create([
                'name' => $name,
                'provider' => $provider,
                'is_active' => true,
            ]);
        }

        foreach (self::FX as $code => $rate) {
            $currencyId = (int) $currencies[$code]->id;
            $margin = $this->openMargin($model->id, $currencyId) ?? $defaultMargin ?? 90.0;

            $this->openPrice(
                $model->id,
                $currencyId,
                round($usd['input'] * $rate, 6),
                round($usd['cache'] * $rate, 6),
                round($usd['output'] * $rate, 6),
                $margin,
                $createdBy ? (int) $createdBy : null
            );
        }

        $this->command?->info("Priced [{$name}] from ".self::START_DATE.'.');
    }

    private function openMargin(int $modelId, int $currencyId): ?float
    {
        $open = $this->openRow($modelId, $currencyId);

        return $open ? (float) $open->margin_percent : null;
    }

    private function openRow(int $modelId, int $currencyId): ?AiModelPrice
    {
        return AiModelPrice::query()
            ->where('ai_model_id', $modelId)
            ->where('currency_id', $currencyId)
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '9999-12-31');
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    private function openPrice(
        int $modelId,
        int $currencyId,
        float $costInput,
        float $costCache,
        float $costOutput,
        float $margin,
        ?int $createdBy
    ): void {
        $open = $this->openRow($modelId, $currencyId);
        if ($open && $this->sameCost($open, $costInput, $costCache, $costOutput, $margin)) {
            return;
        }

        if ($open && $open->start_date?->toDateString() >= self::START_DATE) {
            $open->fill([
                'cost_per_1m_input' => $costInput,
                'cost_per_1m_cache' => $costCache,
                'cost_per_1m_output' => $costOutput,
                'margin_percent' => $margin,
            ])->save();

            return;
        }

        AiModelPrice::query()
            ->where('ai_model_id', $modelId)
            ->where('currency_id', $currencyId)
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '9999-12-31');
            })
            ->where('start_date', '<', self::START_DATE)
            ->update([
                'end_date' => Carbon::parse(self::START_DATE)->subDay()->toDateString(),
            ]);

        AiModelPrice::query()->create([
            'ai_model_id' => $modelId,
            'currency_id' => $currencyId,
            'cost_per_1m_input' => $costInput,
            'cost_per_1m_cache' => $costCache,
            'cost_per_1m_output' => $costOutput,
            'margin_percent' => $margin,
            'start_date' => self::START_DATE,
            'end_date' => null,
            'created_by' => $createdBy,
        ]);
    }

    private function sameCost(AiModelPrice $price, float $input, float $cache, float $output, float $margin): bool
    {
        return abs((float) $price->cost_per_1m_input - $input) < 0.000001
            && abs((float) $price->cost_per_1m_cache - $cache) < 0.000001
            && abs((float) $price->cost_per_1m_output - $output) < 0.000001
            && abs((float) $price->margin_percent - $margin) < 0.001;
    }
}
