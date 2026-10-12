<div class="form-group mb-2">
    <label>ИИ-тариф</label>
    <select name="plan_id" class="form-control">
        @foreach($aiPlans as $plan)
            <option value="{{ $plan->id }}" {{ (int) (optional($price)->plan_id ?? 0) === (int) $plan->id ? 'selected' : '' }}>
                {{ $plan->name }}
                ({{ \App\Models\Ai\AiTariffPlan::categoryLabels()[$plan->category] ?? $plan->category }})
            </option>
        @endforeach
    </select>
</div>

<div class="form-group mb-2">
    <label>Валюта</label>
    <select name="currency_id" class="form-control">
        @foreach($currencies as $currency)
            <option value="{{ $currency->id }}" {{ (int) (optional($price)->currency_id ?? 0) === (int) $currency->id ? 'selected' : '' }}>
                {{ $currency->symbol_code }}
            </option>
        @endforeach
    </select>
</div>

<div class="form-group mb-2">
    <label>Сумма</label>
    <input type="text" class="form-control js-money-input" name="sum" value="{{ optional($price)->sum }}" placeholder="0">
</div>

<div class="form-group mb-2">
    <label>С</label>
    <input type="date" class="form-control" name="start_date" value="{{ optional($price?->start_date)->format('Y-m-d') ?: now()->format('Y-m-d') }}">
</div>

<div class="form-group mb-2">
    <label>До</label>
    <input type="date" class="form-control" name="end_date" value="{{ optional($price?->end_date)->format('Y-m-d') }}">
</div>
