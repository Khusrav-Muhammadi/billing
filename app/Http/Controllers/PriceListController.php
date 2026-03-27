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

        Price::create($data);

        return redirect()->back();
    }

    public function update(Price $price, UpdateRequest $request)
    {
        $data = $request->validated();

        $price->update($data);

        return redirect()->route('price_list.index');
    }

    public function destroy(Price $price)
    {
        $price->delete();

        return redirect()->back();
    }

}
