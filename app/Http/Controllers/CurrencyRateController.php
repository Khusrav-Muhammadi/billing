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

        if ((int) $currency->id === (int) $usdCurrency->id) {
            return redirect()->route('currency-rate.index')->withErrors(['currency' => 'Для USD курс не настраивается']);
        }

        $rates = CurrencyRate::query()
            ->where('base_currency_id', $usdCurrency->id)
            ->where('quote_currency_id', $currency->id)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.currency_rates.show', compact('currency', 'rates'));
    }

    public function store(Currency $currency, StoreRequest $request)
    {
        $usdCurrency = $this->getUsdCurrency();
        $data = $request->validated();

        if ((int) $currency->id === (int) $usdCurrency->id) {
            return redirect()->route('currency-rate.index')->withErrors(['currency' => 'Для USD курс не настраивается']);
        }

        CurrencyRate::query()->create([
            'base_currency_id' => $usdCurrency->id,
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
}
