<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiImplementationPrice;
use Illuminate\Http\Request;

class AiImplementationPriceController extends Controller
{
    private const OPEN_ENDED_END_DATE = '9999-12-31';

    public function store(Request $request)
    {
        AiImplementationPrice::query()->create($this->validated($request));

        return redirect()->back();
    }

    public function update(AiImplementationPrice $aiImplementationPrice, Request $request)
    {
        $aiImplementationPrice->update($this->validated($request));

        return redirect()->back();
    }

    public function destroy(AiImplementationPrice $aiImplementationPrice)
    {
        $aiImplementationPrice->delete();

        return redirect()->back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:ai_tariff_plans,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'sum' => ['required'],
        ]);

        $data['end_date'] = $data['end_date'] ?? self::OPEN_ENDED_END_DATE;
        $data['sum'] = $this->normalizeDecimal((string) $data['sum']);

        return $data;
    }

    private function normalizeDecimal(string $value): string
    {
        $normalized = str_replace([' ', "\u{00A0}"], '', trim($value));
        $normalized = str_replace(',', '.', $normalized);

        return $normalized;
    }
}
