<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiTokenPricing;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiTokenPricingController extends Controller
{
    public function index()
    {
        // Текущие активные цены (grouped by model_name)
        $currentPricing = AiTokenPricing::query()
            ->whereNull('effective_to')
            ->with('costCurrency', 'priceCurrency', 'creator')
            ->orderBy('provider')
            ->orderBy('model_name')
            ->get();

        // История (уже закрытые)
        $historyPricing = AiTokenPricing::query()
            ->whereNotNull('effective_to')
            ->with('costCurrency', 'priceCurrency', 'creator')
            ->orderBy('model_name')
            ->orderByDesc('effective_from')
            ->get();

        $currencies = Currency::query()->orderBy('name')->get();

        return view('admin.ai-token-pricing.index', compact('currentPricing', 'historyPricing', 'currencies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider'           => 'required|string|max:50',
            'model_name'         => 'required|string|max:50',
            'cost_currency_id'   => 'required|integer|exists:currencies,id',
            'cost_per_1m_input'  => 'required|numeric|min:0',
            'cost_per_1m_output' => 'required|numeric|min:0',
            'margin_percent'     => 'required|numeric|min:0|max:99.99',
            'price_currency_id'  => 'required|integer|exists:currencies,id',
            'is_active'          => 'nullable|boolean',
        ]);

        AiTokenPricing::updatePrice(
            array_merge($data, ['is_active' => (bool) ($data['is_active'] ?? true)]),
            Auth::id()
        );

        return redirect()->route('ai-token-pricing.index')->with('success', 'Цена модели добавлена.');
    }

    public function update(Request $request, AiTokenPricing $aiTokenPricing): RedirectResponse
    {
        $data = $request->validate([
            'provider'           => 'required|string|max:50',
            'model_name'         => 'required|string|max:50',
            'cost_currency_id'   => 'required|integer|exists:currencies,id',
            'cost_per_1m_input'  => 'required|numeric|min:0',
            'cost_per_1m_output' => 'required|numeric|min:0',
            'margin_percent'     => 'required|numeric|min:0|max:99.99',
            'price_currency_id'  => 'required|integer|exists:currencies,id',
            'is_active'          => 'nullable|boolean',
        ]);

        // SCD Type 2 — закрываем старую, создаём новую
        AiTokenPricing::updatePrice(
            array_merge($data, ['is_active' => (bool) ($data['is_active'] ?? true)]),
            Auth::id()
        );

        return redirect()->route('ai-token-pricing.index')->with('success', 'Цена модели обновлена.');
    }
}
