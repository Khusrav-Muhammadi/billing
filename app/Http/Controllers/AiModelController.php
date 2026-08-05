<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelPrice;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AiModelController extends Controller
{
    public function index()
    {
        $models = AiModel::query()
            ->orderBy('provider')
            ->orderBy('name')
            ->get();
        $providers = AiModel::$providers;

        return view('admin.ai-models.index', compact('models', 'providers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100|unique:ai_models,name',
            'provider'  => ['required', Rule::in(array_keys(AiModel::$providers))],
            'is_active' => 'nullable|boolean',
        ]);

        AiModel::query()->create([
            'name'      => $data['name'],
            'provider'  => $data['provider'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('ai-model.index')->with('success', 'Модель добавлена.');
    }

    public function update(Request $request, AiModel $aiModel): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100', Rule::unique('ai_models', 'name')->ignore($aiModel->id)],
            'provider'  => ['required', Rule::in(array_keys(AiModel::$providers))],
            'is_active' => 'nullable|boolean',
        ]);

        $aiModel->update([
            'name'      => $data['name'],
            'provider'  => $data['provider'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('ai-model.index')->with('success', 'Модель обновлена.');
    }

    public function destroy(AiModel $aiModel): RedirectResponse
    {
        $aiModel->delete();

        return redirect()->route('ai-model.index')->with('success', 'Модель удалена.');
    }

    // ─── Цены модели ─────────────────────────────────────────────────────────

    public function pricesIndex(AiModel $aiModel)
    {
        $prices = $aiModel->prices()->with('currency', 'creator')->get();
        $currencies = Currency::query()->orderBy('name')->get();

        // Дубли не трогаем: «Текущая» — только последняя по id на валюту.
        $currentPriceIds = $prices
            ->filter(fn (AiModelPrice $p) => $p->isCurrent())
            ->groupBy('currency_id')
            ->map(fn ($group) => $group->sortByDesc('id')->first()?->id)
            ->filter()
            ->values()
            ->all();

        return view('admin.ai-models.prices', compact('aiModel', 'prices', 'currencies', 'currentPriceIds'));
    }

    public function pricesStore(Request $request, AiModel $aiModel): RedirectResponse
    {
        $data = $request->validate([
            'currency_id' => 'required|integer|exists:currencies,id',
            'cost_per_1m_input' => 'required|numeric|min:0',
            'cost_per_1m_cache' => 'required|numeric|min:0',
            'cost_per_1m_output' => 'required|numeric|min:0',
            'margin_percent' => 'required|numeric|min:0|max:99.99',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        DB::transaction(function () use ($aiModel, $data): void {
            // Закрываем только более ранние открытые цены той же валюты (ничего не удаляем).
            AiModelPrice::query()
                ->where('ai_model_id', $aiModel->id)
                ->where('currency_id', $data['currency_id'])
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '9999-12-31');
                })
                ->where('start_date', '<', $data['start_date'])
                ->update([
                    'end_date' => \Illuminate\Support\Carbon::parse($data['start_date'])->subDay()->toDateString(),
                ]);

            AiModelPrice::query()->create([
                'ai_model_id' => $aiModel->id,
                'currency_id' => $data['currency_id'],
                'cost_per_1m_input' => $data['cost_per_1m_input'],
                'cost_per_1m_cache' => $data['cost_per_1m_cache'],
                'cost_per_1m_output' => $data['cost_per_1m_output'],
                'margin_percent' => $data['margin_percent'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('ai-model.prices.index', $aiModel)->with('success', 'Цена добавлена.');
    }

    public function pricesUpdate(Request $request, AiModelPrice $price): RedirectResponse
    {
        $data = $request->validate([
            'currency_id' => 'required|integer|exists:currencies,id',
            'cost_per_1m_input' => 'required|numeric|min:0',
            'cost_per_1m_cache' => 'required|numeric|min:0',
            'cost_per_1m_output' => 'required|numeric|min:0',
            'margin_percent' => 'required|numeric|min:0|max:99.99',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $price->update([
            'currency_id' => $data['currency_id'],
            'cost_per_1m_input' => $data['cost_per_1m_input'],
            'cost_per_1m_cache' => $data['cost_per_1m_cache'],
            'cost_per_1m_output' => $data['cost_per_1m_output'],
            'margin_percent' => $data['margin_percent'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
        ]);

        return redirect()->route('ai-model.prices.index', $price->ai_model_id)->with('success', 'Цена обновлена.');
    }

    public function pricesDestroy(AiModelPrice $price): RedirectResponse
    {
        $modelId = $price->ai_model_id;
        $price->delete();

        return redirect()->route('ai-model.prices.index', $modelId)->with('success', 'Цена удалена.');
    }
}
