<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrencyRate\StoreRequest;
use App\Http\Requests\CurrencyRate\UpdateRequest;
use App\Models\Currency;
use App\Models\CurrencyRate;

class CurrencyRateController extends Controller
{
    public function index()
    {
        $currencies = Currency::query()
            ->where('symbol_code', '!=', 'USD')
            ->orderBy('name')
            ->get();

        return view('admin.currency_rates.index', compact('currencies'));
    }

    public function show(Currency $currency)
    {
        $usdCurrency = $this->getUsdCurrency();
        $baseCurrencies = $this->getSupportedBaseCurrencies();
        $baseCurrencyIds = $baseCurrencies->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rates = CurrencyRate::query()
            ->with('baseCurrency:id,name,symbol_code')
            ->when(!empty($baseCurrencyIds), function ($query) use ($baseCurrencyIds) {
                $query->whereIn('base_currency_id', $baseCurrencyIds);
            })
            ->where('quote_currency_id', $currency->id)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->get();

        $defaultBaseCurrencyId = (int) $usdCurrency->id;

        return view('admin.currency_rates.show', compact('currency', 'rates', 'baseCurrencies', 'defaultBaseCurrencyId'));
    }

    public function store(Currency $currency, StoreRequest $request)
    {
        $usdCurrency = $this->getUsdCurrency();
        $data = $request->validated();
        $baseCurrencyId = isset($data['base_currency_id']) && $data['base_currency_id']
            ? (int) $data['base_currency_id']
            : (int) $usdCurrency->id;

        CurrencyRate::query()->create([
            'base_currency_id' => $baseCurrencyId,
            'quote_currency_id' => $currency->id,
            'rate' => $data['rate'],
            'rate_date' => $data['rate_date'],
        ]);

        return redirect()->route('currency-rate.show', $currency->id);
    }

    public function update(CurrencyRate $currencyRate, UpdateRequest $request)
    {
        $data = $request->validated();

        $currencyRate->update([
            'rate' => $data['rate'],
            'rate_date' => $data['rate_date'],
        ]);

        return redirect()->route('currency-rate.show', $currencyRate->quote_currency_id);
    }

    public function destroy(CurrencyRate $currencyRate)
    {
        $quoteCurrencyId = $currencyRate->quote_currency_id;
        $currencyRate->delete();

        return redirect()->route('currency-rate.show', $quoteCurrencyId);
    }

    private function getUsdCurrency(): Currency
    {
        return Currency::query()->where('symbol_code', 'USD')->firstOrFail();
    }

    private function getSupportedBaseCurrencies()
    {
        return Currency::query()
            ->whereRaw('UPPER(symbol_code) IN (?, ?)', ['USD', 'UZS'])
            ->orderByRaw("CASE WHEN UPPER(symbol_code) = 'USD' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'symbol_code']);
    }
}
