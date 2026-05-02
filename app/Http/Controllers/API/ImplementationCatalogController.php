<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ImplementationDiscountCap;
use App\Models\Price;
use App\Models\Tariff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ImplementationCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tariffId = (int)$request->query('tariff_id', 0);

        $prices = Price::query()
            ->with([
                'tariff:id,name,is_tariff',
                'currency:id,symbol_code,name',
            ])
            ->whereNull('organization_id')
            ->where('kind', 'implementation')
            ->when($tariffId > 0, static function ($query) use ($tariffId) {
                $query->where('tariff_id', $tariffId);
            })
            ->orderBy('tariff_id')
            ->orderBy('currency_id')
            ->orderBy('start_date')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $tariffs = Tariff::query()
            ->where('is_tariff', true)
            ->when($tariffId > 0, static function ($query) use ($tariffId) {
                $query->whereKey($tariffId);
            })
            ->orderBy('id')
            ->get(['id', 'name', 'is_tariff']);

        $pricesByTariff = $prices
            ->groupBy(static fn (Price $price): int => (int)$price->tariff_id)
            ->map(static fn ($rows) => $rows->map(static fn (Price $price): array => self::priceRow($price))->values()->all());

        $discountCaps = $this->discountCaps();

        return response()->json([
            'tariffs' => $tariffs
                ->map(static fn (Tariff $tariff): array => [
                    'id' => (int)$tariff->id,
                    'name' => (string)$tariff->name,
                    'implementation_prices' => $pricesByTariff->get((int)$tariff->id, []),
                ])
                ->values()
                ->all(),
            'implementation_prices' => $prices
                ->map(static fn (Price $price): array => self::priceRow($price))
                ->values()
                ->all(),
            'implementation_discount_caps' => $discountCaps,
        ]);
    }

    private static function priceRow(Price $price): array
    {
        return [
            'id' => (int)$price->id,
            'tariff_id' => (int)$price->tariff_id,
            'tariff_name' => (string)($price->tariff?->name ?? ''),
            'currency_id' => (int)$price->currency_id,
            'currency_code' => strtoupper((string)($price->currency?->symbol_code ?? '')),
            'currency_name' => (string)($price->currency?->name ?? ''),
            'start_date' => $price->start_date,
            'end_date' => $price->date,
            'sum' => (float)$price->sum,
            'created_at' => optional($price->created_at)->toISOString(),
            'updated_at' => optional($price->updated_at)->toISOString(),
        ];
    }

    private function discountCaps(): array
    {
        if (!Schema::hasTable('implementation_discount_caps')) {
            return [];
        }

        $hasTariffId = Schema::hasColumn('implementation_discount_caps', 'tariff_id');
        $hasCurrencyCode = Schema::hasColumn('implementation_discount_caps', 'currency_code');

        return ImplementationDiscountCap::query()
            ->orderBy('period_type')
            ->orderBy($hasCurrencyCode ? 'currency_code' : 'id')
            ->orderBy('id')
            ->get()
            ->map(static function (ImplementationDiscountCap $cap) use ($hasTariffId, $hasCurrencyCode): array {
                return [
                    'id' => (int)$cap->id,
                    'tariff_id' => $hasTariffId ? (int)($cap->tariff_id ?? 0) : null,
                    'period_type' => (string)$cap->period_type,
                    'currency_code' => $hasCurrencyCode ? strtoupper((string)($cap->currency_code ?? '')) : null,
                    'max_percent' => (float)$cap->max_percent,
                    'is_active' => (bool)$cap->is_active,
                    'created_at' => optional($cap->created_at)->toISOString(),
                    'updated_at' => optional($cap->updated_at)->toISOString(),
                ];
            })
            ->values()
            ->all();
    }
}
