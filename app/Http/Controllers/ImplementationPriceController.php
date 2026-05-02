<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Price;
use App\Models\Tariff;
use Illuminate\Http\Request;

class ImplementationPriceController extends Controller
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
        $prices = Price::query()
            ->with(['tariff', 'currency'])
            ->whereNull('organization_id')
            ->where('kind', 'implementation')
            ->orderByDesc('id')
            ->get();

        $tariffs = Tariff::query()
            ->where('is_tariff', true)
            ->orderBy('name')
            ->get();

        $currencies = Currency::query()->orderBy('name')->get();

        return view('admin.implementation.prices', compact('prices', 'tariffs', 'currencies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tariff_id' => ['required', 'exists:tariffs,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'start_date' => ['required', 'date'],
            'date' => ['nullable', 'date'],
            'sum' => ['required'],
        ]);

        $data['organization_id'] = null;
        $data['kind'] = 'implementation';
        $data['date'] = $data['date'] ?? self::OPEN_ENDED_END_DATE;
        $data['sum'] = $this->normalizeDecimal((string)$data['sum']);

        Price::query()->create($data);

        return redirect()->back();
    }

    public function update(Price $price, Request $request)
    {
        if ($price->kind !== 'implementation') {
            abort(404);
        }

        $data = $request->validate([
            'tariff_id' => ['required', 'exists:tariffs,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'start_date' => ['required', 'date'],
            'date' => ['nullable', 'date'],
            'sum' => ['required'],
        ]);

        $data['organization_id'] = null;
        $data['kind'] = 'implementation';
        $data['date'] = $data['date'] ?? self::OPEN_ENDED_END_DATE;
        $data['sum'] = $this->normalizeDecimal((string)$data['sum']);

        $price->update($data);

        return redirect()->back();
    }

    public function destroy(Price $price)
    {
        if ($price->kind !== 'implementation') {
            abort(404);
        }

        $price->delete();

        return redirect()->back();
    }
}

