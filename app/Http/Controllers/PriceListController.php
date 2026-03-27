<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriceList\StoreRequest;
use App\Http\Requests\PriceList\UpdateRequest;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Price;
use App\Models\Tariff;

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

        $organizations = Organization::query()->orderBy('name')->get();
        $tariffs = Tariff::query()->orderBy('name')->get();
        $currencies = Currency::query()->orderBy('name')->get();

        return view('admin.tariffs.price_list', compact('tariffs', 'priceLists', 'organizations', 'currencies'));
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

}
