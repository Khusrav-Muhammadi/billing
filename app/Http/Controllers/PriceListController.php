<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriceList\StoreRequest;
use App\Http\Requests\PriceList\UpdateRequest;
use App\Models\Currency;
use App\Models\ExtraUserPriceTier;
use App\Models\Organization;
use App\Models\Price;
use App\Models\Tariff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PriceListController extends Controller
{
    private const OPEN_ENDED_END_DATE = '9999-12-31';

    private function normalizeDecimal(string $value): string
    {
        $v = trim($value);
        $v = str_replace([' ', "\u{00A0}"], '', $v);
        $v = str_replace(',', '.', $v);
        return $v;
    }

    public function index()
    {
        $priceLists = Price::query()
            ->with(['tariff', 'organization', 'currency'])
            ->orderByDesc('id')
            ->get();

        $extraUserPriceTiers = ExtraUserPriceTier::query()
            ->with(['tariff', 'organization', 'currency'])
            ->orderByDesc('id')
            ->get();

        $organizations = Organization::query()->orderBy('name')->get();
        $tariffs = Tariff::query()->orderBy('name')->get();
        $baseTariffs = Tariff::query()
            ->where('is_tariff', true)
            ->where(function ($query) {
                $query->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->orderBy('name')
            ->get();
        $currencies = Currency::query()->orderBy('name')->get();

        return view('admin.tariffs.price_list', compact('tariffs', 'baseTariffs', 'priceLists', 'extraUserPriceTiers', 'organizations', 'currencies'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $data['kind'] = 'base';
        $data['date'] = $data['date'] ?? self::OPEN_ENDED_END_DATE;
        $data['sum'] = $this->normalizeDecimal((string) $data['sum']);

        Price::create($data);

        return redirect()->back();
    }

    public function update(Price $price, UpdateRequest $request)
    {
        $data = $request->validated();
        $data['kind'] = 'base';
        $data['date'] = $data['date'] ?? self::OPEN_ENDED_END_DATE;
        $data['sum'] = $this->normalizeDecimal((string) $data['sum']);

        $price->update($data);

        return redirect()->route('price_list.index');
    }

    public function destroy(Price $price)
    {
        $price->delete();

        return redirect()->back();
    }

    public function storeExtraUserTier(Request $request)
    {
        ExtraUserPriceTier::create($this->validateExtraUserTier($request));

        return redirect()->back();
    }

    public function updateExtraUserTier(ExtraUserPriceTier $extraUserPriceTier, Request $request)
    {
        $extraUserPriceTier->update($this->validateExtraUserTier($request));

        return redirect()->route('price_list.index');
    }

    public function destroyExtraUserTier(ExtraUserPriceTier $extraUserPriceTier)
    {
        $extraUserPriceTier->delete();

        return redirect()->back();
    }

    private function validateExtraUserTier(Request $request): array
    {
        $data = $request->validate([
            'tariff_id' => [
                'required',
                Rule::exists('tariffs', 'id')->where(static function ($query) {
                    $query->where('is_tariff', true)
                        ->where(function ($query) {
                            $query->whereNull('is_extra_user')->orWhere('is_extra_user', false);
                        });
                }),
            ],
            'organization_id' => ['nullable', Rule::exists('organizations', 'id')],
            'currency_id' => ['required', Rule::exists('currencies', 'id')],
            'min_total_users' => ['required', 'integer', 'min:1'],
            'max_total_users' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['required', 'regex:/^\\d+(?:[\\.,]\\d{1,4})?$/'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        if (!empty($data['max_total_users']) && (int)$data['max_total_users'] < (int)$data['min_total_users']) {
            throw ValidationException::withMessages([
                'max_total_users' => 'Значение "до пользователей" не может быть меньше значения "от пользователей".',
            ]);
        }

        $data['unit_price'] = $this->normalizeDecimal((string)$data['unit_price']);

        return $data;
    }
}
