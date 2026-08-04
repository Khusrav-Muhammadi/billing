<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiTariffPlan;
use App\Models\Ai\AiTariffPlanPeriod;
use App\Models\Ai\AiTariffPlanPrice;
use App\Models\Currency;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AiTariffController extends Controller
{
    // ─── Планы ───────────────────────────────────────────────────────────────

    public function index()
    {
        $plans = AiTariffPlan::query()
            ->with(['activePeriods', 'currentPrice.currency', 'aiModel'])
            ->orderBy('name')
            ->get();

        $aiModels = AiModel::query()
            ->where('is_active', true)
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        return view('admin.ai-tariffs.index', compact('plans', 'aiModels'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100|unique:ai_tariff_plans,name',
            'ai_model_id'  => 'nullable|integer|exists:ai_models,id',
            'is_active'    => 'nullable|boolean',
        ]);

        AiTariffPlan::query()->create([
            'name'         => $data['name'],
            'ai_model_id'  => $data['ai_model_id'] ?? null,
            'is_active'    => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('ai-tariff.index')->with('success', 'Тарифный план создан.');
    }

    public function update(Request $request, AiTariffPlan $aiTariff): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100', Rule::unique('ai_tariff_plans', 'name')->ignore($aiTariff->id)],
            'ai_model_id'  => 'nullable|integer|exists:ai_models,id',
            'is_active'    => 'nullable|boolean',
        ]);

        $aiTariff->update([
            'name'         => $data['name'],
            'ai_model_id'  => $data['ai_model_id'] ?? null,
            'is_active'    => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('ai-tariff.index')->with('success', 'Тарифный план обновлён.');
    }

    public function destroy(AiTariffPlan $aiTariff): RedirectResponse
    {
        $aiTariff->delete();

        return redirect()->route('ai-tariff.index')->with('success', 'Тарифный план удалён.');
    }

    // ─── Цены тарифа ─────────────────────────────────────────────────────────

    public function pricesIndex(AiTariffPlan $aiTariff)
    {
        $prices    = $aiTariff->prices()->with('currency', 'creator')->get();
        $currencies = Currency::query()->orderBy('name')->get();

        return view('admin.ai-tariffs.prices', compact('aiTariff', 'prices', 'currencies'));
    }

    public function pricesStore(Request $request, AiTariffPlan $aiTariff): RedirectResponse
    {
        $data = $request->validate([
            'currency_id'   => 'required|integer|exists:currencies,id',
            'price_monthly' => 'required|numeric|min:0.01',
            'start_date'    => 'required|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
        ]);

        DB::transaction(function () use ($aiTariff, $data): void {
            // Закрываем предыдущую открытую цену той же валюты
            AiTariffPlanPrice::query()
                ->where('plan_id', $aiTariff->id)
                ->where('currency_id', $data['currency_id'])
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '9999-12-31');
                })
                ->where('start_date', '<', $data['start_date'])
                ->update([
                    'end_date' => \Illuminate\Support\Carbon::parse($data['start_date'])->subDay()->toDateString(),
                ]);

            AiTariffPlanPrice::query()->create([
                'plan_id'       => $aiTariff->id,
                'currency_id'   => $data['currency_id'],
                'price_monthly' => $data['price_monthly'],
                'start_date'    => $data['start_date'],
                'end_date'      => $data['end_date'] ?? null,
                'created_by'    => Auth::id(),
            ]);

            // Синхронизируем price_total активных периодов
            $monthly = (float) $data['price_monthly'];
            AiTariffPlanPeriod::query()
                ->where('plan_id', $aiTariff->id)
                ->whereNull('valid_to')
                ->each(function (AiTariffPlanPeriod $period) use ($monthly): void {
                    $period->updateQuietly([
                        'price_total' => round(
                            $monthly * (int) $period->months * (1 - (float) $period->discount_percent / 100),
                            4
                        ),
                    ]);
                });
        });

        return redirect()->route('ai-tariff.prices.index', $aiTariff)->with('success', 'Цена добавлена.');
    }

    public function pricesUpdate(Request $request, AiTariffPlanPrice $price): RedirectResponse
    {
        $data = $request->validate([
            'currency_id'   => 'required|integer|exists:currencies,id',
            'price_monthly' => 'required|numeric|min:0.01',
            'start_date'    => 'required|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
        ]);

        $price->update([
            'currency_id'   => $data['currency_id'],
            'price_monthly' => $data['price_monthly'],
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'] ?? null,
        ]);

        return redirect()->route('ai-tariff.prices.index', $price->plan_id)->with('success', 'Цена обновлена.');
    }

    public function pricesDestroy(AiTariffPlanPrice $price): RedirectResponse
    {
        $planId = $price->plan_id;
        $price->delete();

        return redirect()->route('ai-tariff.prices.index', $planId)->with('success', 'Цена удалена.');
    }

    // ─── Периоды / скидки ────────────────────────────────────────────────────

    public function periodsIndex(AiTariffPlan $aiTariff)
    {
        // Все периоды (история): сгруппировать по months для отображения
        $periods = $aiTariff->periods()->with('creator')->orderBy('months')->orderByDesc('valid_from')->get();

        $currentPrice = $aiTariff->prices()->current()->first();

        return view('admin.ai-tariffs.periods', compact('aiTariff', 'periods', 'currentPrice'));
    }

    public function periodsStore(Request $request, AiTariffPlan $aiTariff): RedirectResponse
    {
        $data = $request->validate([
            'months'           => 'required|integer|min:1|max:120',
            'discount_percent' => 'required|numeric|min:0|max:99.99',
            'price_total'      => 'required|numeric|min:0',
            'valid_from'       => 'required|date',
        ]);

        AiTariffPlanPeriod::updateDiscount(
            planId:          $aiTariff->id,
            months:          (int) $data['months'],
            discountPercent: (float) $data['discount_percent'],
            priceTotal:      (float) $data['price_total'],
            userId:          Auth::id(),
        );

        // Вставляем valid_from из формы
        AiTariffPlanPeriod::query()
            ->where('plan_id', $aiTariff->id)
            ->where('months', $data['months'])
            ->whereNull('valid_to')
            ->update(['valid_from' => $data['valid_from']]);

        return redirect()->route('ai-tariff.periods.index', $aiTariff)->with('success', 'Период / скидка добавлены.');
    }

    public function periodsDestroy(AiTariffPlanPeriod $period): RedirectResponse
    {
        $planId = $period->plan_id;
        $period->delete();

        return redirect()->route('ai-tariff.periods.index', $planId)->with('success', 'Период удалён.');
    }
}
