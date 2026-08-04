@extends('layouts.app')

@section('title') ИИ — Цены токенов @endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- ── Текущие цены ──────────────────────────────────────────────────────── --}}
<div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="card-title mb-0">ИИ — Себестоимость и цены токенов</h4>
        <a href="#" data-bs-toggle="modal" data-bs-target="#createPricingModal" class="btn btn-primary">Добавить модель</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>№</th>
                <th>Провайдер</th>
                <th>Модель</th>
                <th>Себест. вход<br><small class="text-muted">за 1M токенов</small></th>
                <th>Себест. выход<br><small class="text-muted">за 1M токенов</small></th>
                <th>Маржа %</th>
                <th>Цена вход<br><small class="text-muted">за 1M токенов</small></th>
                <th>Цена выход<br><small class="text-muted">за 1M токенов</small></th>
                <th>Вал. себест.</th>
                <th>Вал. цены</th>
                <th>Статус</th>
                <th>С</th>
                <th>Добавил</th>
                <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            @forelse($currentPricing as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row->provider }}</td>
                    <td><code>{{ $row->model_name }}</code></td>
                    <td>{{ number_format($row->cost_per_1m_input, 4) }}</td>
                    <td>{{ number_format($row->cost_per_1m_output, 4) }}</td>
                    <td>
                        <span class="badge bg-info text-dark">{{ $row->margin_percent }}%</span>
                    </td>
                    <td><strong>{{ number_format($row->price_per_1m_input, 4) }}</strong></td>
                    <td><strong>{{ number_format($row->price_per_1m_output, 4) }}</strong></td>
                    <td>{{ $row->costCurrency?->name }}</td>
                    <td>{{ $row->priceCurrency?->name }}</td>
                    <td>
                        @if($row->is_active)
                            <span class="badge bg-success">Активна</span>
                        @else
                            <span class="badge bg-secondary">Отключена</span>
                        @endif
                    </td>
                    <td>{{ $row->effective_from ? $row->effective_from->format('d.m.Y') : '—' }}</td>
                    <td>{{ $row->creator?->name ?? '—' }}</td>
                    <td>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editPricing{{ $row->id }}">
                            <i class="mdi mdi-pencil-box-outline" style="font-size: 24px"></i>
                        </a>
                    </td>
                </tr>

                {{-- Модал редактирования (SCD Type 2 — создаёт новую запись) --}}
                <div class="modal fade" id="editPricing{{ $row->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <form action="{{ route('ai-token-pricing.update', $row) }}" method="POST">
                            @csrf @method('PATCH')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Обновить цены: <code>{{ $row->model_name }}</code></h5>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info py-2 mb-3" style="font-size: 13px;">
                                        Изменение цен создаёт новую версию записи (история сохраняется)
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Провайдер <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="provider" value="{{ $row->provider }}" required maxlength="50">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Модель <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="model_name" value="{{ $row->model_name }}" required maxlength="50">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Себест. вход / 1M токенов <span class="text-danger">*</span></label>
                                                <input type="text" inputmode="decimal" class="form-control edit-cost-input" name="cost_per_1m_input" value="{{ $row->cost_per_1m_input }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Себест. выход / 1M токенов <span class="text-danger">*</span></label>
                                                <input type="text" inputmode="decimal" class="form-control edit-cost-output" name="cost_per_1m_output" value="{{ $row->cost_per_1m_output }}" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Маржа % <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" min="0" max="99.99" class="form-control edit-margin" name="margin_percent" value="{{ $row->margin_percent }}" required>
                                                <small class="text-muted">Не более 99.99%</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Расч. цена вход</label>
                                                <input type="text" class="form-control edit-price-preview-input" readonly placeholder="авто">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Расч. цена выход</label>
                                                <input type="text" class="form-control edit-price-preview-output" readonly placeholder="авто">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Валюта себестоимости <span class="text-danger">*</span></label>
                                                <select class="form-control" name="cost_currency_id" required>
                                                    @foreach($currencies as $currency)
                                                        <option value="{{ $currency->id }}" {{ $row->cost_currency_id == $currency->id ? 'selected' : '' }}>
                                                            {{ $currency->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Валюта цены продажи <span class="text-danger">*</span></label>
                                                <select class="form-control" name="price_currency_id" required>
                                                    @foreach($currencies as $currency)
                                                        <option value="{{ $currency->id }}" {{ $row->price_currency_id == $currency->id ? 'selected' : '' }}>
                                                            {{ $currency->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editIsActive{{ $row->id }}" {{ $row->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label" for="editIsActive{{ $row->id }}">Активна</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="submit" class="btn btn-primary">Сохранить новую версию</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <tr>
                    <td colspan="14" class="text-center text-muted py-4">Цены моделей не добавлены</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── История изменений ─────────────────────────────────────────────────── --}}
@if($historyPricing->count())
<div class="card-body mt-2">
    <h5 class="card-title text-muted">
        <a class="text-muted text-decoration-none" data-bs-toggle="collapse" href="#historyTable" role="button">
            История изменений цен ({{ $historyPricing->count() }})
            <i class="mdi mdi-chevron-down"></i>
        </a>
    </h5>
    <div class="collapse" id="historyTable">
        <div class="table-responsive">
            <table class="table table-sm text-muted">
                <thead>
                <tr>
                    <th>Модель</th>
                    <th>Себест. вход</th>
                    <th>Себест. выход</th>
                    <th>Маржа %</th>
                    <th>Цена вход</th>
                    <th>Цена выход</th>
                    <th>С</th>
                    <th>По</th>
                    <th>Добавил</th>
                </tr>
                </thead>
                <tbody>
                @foreach($historyPricing as $row)
                    <tr>
                        <td><code>{{ $row->model_name }}</code></td>
                        <td>{{ number_format($row->cost_per_1m_input, 4) }}</td>
                        <td>{{ number_format($row->cost_per_1m_output, 4) }}</td>
                        <td>{{ $row->margin_percent }}%</td>
                        <td>{{ number_format($row->price_per_1m_input, 4) }}</td>
                        <td>{{ number_format($row->price_per_1m_output, 4) }}</td>
                        <td>{{ $row->effective_from ? $row->effective_from->format('d.m.Y') : '—' }}</td>
                        <td>{{ $row->effective_to ? $row->effective_to->format('d.m.Y') : '—' }}</td>
                        <td>{{ $row->creator?->name ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ── Модал создания ──────────────────────────────────────────────────────── --}}
<div class="modal fade" id="createPricingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('ai-token-pricing.store') }}" method="POST" id="createPricingForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить модель / цены токенов</h5>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Провайдер <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('provider') is-invalid @enderror"
                                       name="provider" value="{{ old('provider') }}" required maxlength="50"
                                       placeholder="OpenAI, Anthropic, DeepSeek...">
                                @error('provider')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Название модели <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('model_name') is-invalid @enderror"
                                       name="model_name" value="{{ old('model_name') }}" required maxlength="50"
                                       placeholder="gpt-4o, deepseek-v3...">
                                @error('model_name')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Себест. вход / 1M токенов <span class="text-danger">*</span></label>
                                <input type="text" inputmode="decimal" class="form-control @error('cost_per_1m_input') is-invalid @enderror create-cost-input"
                                       name="cost_per_1m_input" value="{{ old('cost_per_1m_input') }}" required>
                                @error('cost_per_1m_input')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Себест. выход / 1M токенов <span class="text-danger">*</span></label>
                                <input type="text" inputmode="decimal" class="form-control @error('cost_per_1m_output') is-invalid @enderror create-cost-output"
                                       name="cost_per_1m_output" value="{{ old('cost_per_1m_output') }}" required>
                                @error('cost_per_1m_output')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Маржа % <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" max="99.99" class="form-control @error('margin_percent') is-invalid @enderror create-margin"
                                       name="margin_percent" value="{{ old('margin_percent', 0) }}" required>
                                <small class="text-muted">price = cost / (1 − маржа/100)</small>
                                @error('margin_percent')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Расч. цена вход</label>
                                <input type="text" class="form-control create-price-preview-input" readonly placeholder="авто">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Расч. цена выход</label>
                                <input type="text" class="form-control create-price-preview-output" readonly placeholder="авто">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Валюта себестоимости <span class="text-danger">*</span></label>
                                <select class="form-control @error('cost_currency_id') is-invalid @enderror" name="cost_currency_id" required>
                                    <option value="">Выберите валюту</option>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}" {{ old('cost_currency_id') == $currency->id ? 'selected' : '' }}>
                                            {{ $currency->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cost_currency_id')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Валюта цены продажи <span class="text-danger">*</span></label>
                                <select class="form-control @error('price_currency_id') is-invalid @enderror" name="price_currency_id" required>
                                    <option value="">Выберите валюту</option>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}" {{ old('price_currency_id') == $currency->id ? 'selected' : '' }}>
                                            {{ $currency->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('price_currency_id')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="createIsActive" checked>
                        <label class="form-check-label" for="createIsActive">Активна</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function calcPrice(cost, margin) {
        if (margin >= 100) return '—';
        const divisor = 1 - (margin / 100);
        return (parseFloat(cost) / divisor).toFixed(4);
    }

    // ── create form ──
    const createForm = document.getElementById('createPricingForm');
    if (createForm) {
        const ci  = createForm.querySelector('.create-cost-input');
        const co  = createForm.querySelector('.create-cost-output');
        const m   = createForm.querySelector('.create-margin');
        const pi  = createForm.querySelector('.create-price-preview-input');
        const po  = createForm.querySelector('.create-price-preview-output');

        function recalcCreate() {
            const margin = parseFloat(m?.value) || 0;
            if (pi) pi.value = calcPrice(ci?.value || 0, margin);
            if (po) po.value = calcPrice(co?.value || 0, margin);
        }
        [ci, co, m].forEach(el => el?.addEventListener('input', recalcCreate));
        recalcCreate();
    }

    // ── edit forms ──
    document.querySelectorAll('.modal').forEach(function (modal) {
        const ci = modal.querySelector('.edit-cost-input');
        const co = modal.querySelector('.edit-cost-output');
        const m  = modal.querySelector('.edit-margin');
        const pi = modal.querySelector('.edit-price-preview-input');
        const po = modal.querySelector('.edit-price-preview-output');
        if (!ci || !co || !m) return;

        function recalcEdit() {
            const margin = parseFloat(m.value) || 0;
            if (pi) pi.value = calcPrice(ci.value || 0, margin);
            if (po) po.value = calcPrice(co.value || 0, margin);
        }
        [ci, co, m].forEach(el => el.addEventListener('input', recalcEdit));
        modal.addEventListener('shown.bs.modal', recalcEdit);
    });
});
</script>
@endsection
