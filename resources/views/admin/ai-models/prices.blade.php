@extends('layouts.app')

@section('title') Цены модели: {{ $aiModel->name }} @endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="card-title mb-0">Цены модели: <code>{{ $aiModel->name }}</code></h4>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('ai-model.index') }}" class="btn btn-light">Назад</a>
            <a href="#" data-bs-toggle="modal" data-bs-target="#createPriceModal" class="btn btn-primary">Добавить цену</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>№</th>
                <th>Дата начала</th>
                <th>Дата завершения</th>
                <th>Валюта</th>
                <th>Себест. вход / 1M</th>
                <th>Себест. cache / 1M</th>
                <th>Себест. выход / 1M</th>
                <th>Маржа %</th>
                <th>Продажа вход / 1M</th>
                <th>Продажа cache / 1M</th>
                <th>Продажа выход / 1M</th>
                <th>Добавил</th>
                <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            @forelse($prices as $price)
                @php $isCurrent = in_array($price->id, $currentPriceIds ?? [], true); @endphp
                <tr class="{{ $isCurrent ? '' : 'text-muted' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $price->start_date->format('d.m.Y') }}</td>
                    <td>
                        @if(is_null($price->end_date) || $price->end_date->format('Y-m-d') === '9999-12-31')
                            <span class="text-muted">—</span>
                        @else
                            {{ $price->end_date->format('d.m.Y') }}
                        @endif
                    </td>
                    <td>{{ $price->currency?->name }}</td>
                    <td>{{ number_format($price->cost_per_1m_input, 4) }}</td>
                    <td>{{ number_format($price->cost_per_1m_cache, 4) }}</td>
                    <td>{{ number_format($price->cost_per_1m_output, 4) }}</td>
                    <td>{{ number_format($price->margin_percent, 2) }}%</td>
                    <td>
                        <strong>{{ number_format($price->price_per_1m_input, 4) }}</strong>
                        @if($isCurrent)
                            <span class="badge bg-success ms-1">Текущая</span>
                        @endif
                    </td>
                    <td><strong>{{ number_format($price->price_per_1m_cache, 4) }}</strong></td>
                    <td><strong>{{ number_format($price->price_per_1m_output, 4) }}</strong></td>
                    <td>{{ $price->creator?->name ?? '—' }}</td>
                    <td>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editPrice{{ $price->id }}">
                            <i class="mdi mdi-pencil-box-outline" style="font-size: 24px"></i>
                        </a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#deletePrice{{ $price->id }}">
                            <i style="color:red; font-size: 24px" class="mdi mdi-delete"></i>
                        </a>
                    </td>
                </tr>

                <div class="modal fade" id="editPrice{{ $price->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-model.prices.update', $price) }}" method="POST" class="js-price-form">
                            @csrf @method('PATCH')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Изменить цену</h5>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label>Валюта <span class="text-danger">*</span></label>
                                        <select class="form-control" name="currency_id" required>
                                            @foreach($currencies as $currency)
                                                <option value="{{ $currency->id }}" {{ $price->currency_id == $currency->id ? 'selected' : '' }}>
                                                    {{ $currency->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Себестоимость вход / 1M <span class="text-danger">*</span></label>
                                        <input type="text" inputmode="decimal" class="form-control js-cost-in" name="cost_per_1m_input" value="{{ $price->cost_per_1m_input }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Себестоимость cache / 1M <span class="text-danger">*</span></label>
                                        <input type="text" inputmode="decimal" class="form-control js-cost-cache" name="cost_per_1m_cache" value="{{ $price->cost_per_1m_cache }}" required>
                                        <small class="text-muted">Cache-hit токены (часть prompt_tokens)</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Себестоимость выход / 1M <span class="text-danger">*</span></label>
                                        <input type="text" inputmode="decimal" class="form-control js-cost-out" name="cost_per_1m_output" value="{{ $price->cost_per_1m_output }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Маржа % <span class="text-danger">*</span></label>
                                        <input type="text" inputmode="decimal" class="form-control js-margin" name="margin_percent" value="{{ $price->margin_percent }}" required>
                                        <small class="text-muted">Пример: 90 → продажа = себестоимость / 0.1</small>
                                    </div>
                                    <div class="alert alert-light border py-2 mb-3">
                                        Продажа вход: <strong class="js-sell-in">{{ number_format($price->price_per_1m_input, 4) }}</strong>
                                        · cache: <strong class="js-sell-cache">{{ number_format($price->price_per_1m_cache, 4) }}</strong>
                                        · выход: <strong class="js-sell-out">{{ number_format($price->price_per_1m_output, 4) }}</strong>
                                    </div>
                                    <div class="form-group">
                                        <label>Дата начала <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="start_date" value="{{ $price->start_date->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Дата завершения</label>
                                        <input type="date" class="form-control" name="end_date"
                                               value="{{ ($price->end_date && $price->end_date->format('Y-m-d') !== '9999-12-31') ? $price->end_date->format('Y-m-d') : '' }}">
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

                <div class="modal fade" id="deletePrice{{ $price->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-model.prices.destroy', $price) }}" method="POST">
                            @csrf @method('DELETE')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Удалить цену</h5>
                                </div>
                                <div class="modal-body">
                                    Удалить цену
                                    продажа <strong>{{ number_format($price->price_per_1m_input, 4) }} / {{ number_format($price->price_per_1m_cache, 4) }} / {{ number_format($price->price_per_1m_output, 4) }}</strong>
                                    (себест. {{ number_format($price->cost_per_1m_input, 4) }} / {{ number_format($price->cost_per_1m_cache, 4) }} / {{ number_format($price->cost_per_1m_output, 4) }},
                                    маржа {{ number_format($price->margin_percent, 2) }}%)
                                    {{ $price->currency?->name }}?
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="submit" class="btn btn-danger">Удалить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <tr>
                    <td colspan="13" class="text-center text-muted py-4">Цены не добавлены</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createPriceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('ai-model.prices.store', $aiModel) }}" method="POST" class="js-price-form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить цену — <code>{{ $aiModel->name }}</code></h5>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Валюта <span class="text-danger">*</span></label>
                        <select class="form-control @error('currency_id') is-invalid @enderror" name="currency_id" required>
                            <option value="">Выберите валюту</option>
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->id }}" {{ old('currency_id') == $currency->id ? 'selected' : '' }}>
                                    {{ $currency->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('currency_id')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Себестоимость вход / 1M <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control js-cost-in @error('cost_per_1m_input') is-invalid @enderror"
                               name="cost_per_1m_input" value="{{ old('cost_per_1m_input') }}" required>
                        @error('cost_per_1m_input')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Себестоимость cache / 1M <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control js-cost-cache @error('cost_per_1m_cache') is-invalid @enderror"
                               name="cost_per_1m_cache" value="{{ old('cost_per_1m_cache') }}" required>
                        <small class="text-muted">Обычно ~10% от входа (DeepSeek cache hit)</small>
                        @error('cost_per_1m_cache')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Себестоимость выход / 1M <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control js-cost-out @error('cost_per_1m_output') is-invalid @enderror"
                               name="cost_per_1m_output" value="{{ old('cost_per_1m_output') }}" required>
                        @error('cost_per_1m_output')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Маржа % <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control js-margin @error('margin_percent') is-invalid @enderror"
                               name="margin_percent" value="{{ old('margin_percent', '0') }}" required>
                        <small class="text-muted">Пример: 90 → продажа = себестоимость / 0.1</small>
                        @error('margin_percent')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="alert alert-light border py-2 mb-3">
                        Продажа вход: <strong class="js-sell-in">—</strong>
                        · cache: <strong class="js-sell-cache">—</strong>
                        · выход: <strong class="js-sell-out">—</strong>
                    </div>
                    <div class="form-group">
                        <label>Дата начала <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                               name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
                        @error('start_date')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Дата завершения</label>
                        <input type="date" class="form-control" name="end_date" value="{{ old('end_date') }}">
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
(function () {
    function parseNum(v) {
        if (v == null) return NaN;
        return parseFloat(String(v).replace(',', '.').trim());
    }

    function formatNum(n) {
        if (!isFinite(n)) return '—';
        return n.toFixed(4);
    }

    function recalc(form) {
        var costIn = parseNum(form.querySelector('.js-cost-in') && form.querySelector('.js-cost-in').value);
        var costCache = parseNum(form.querySelector('.js-cost-cache') && form.querySelector('.js-cost-cache').value);
        var costOut = parseNum(form.querySelector('.js-cost-out') && form.querySelector('.js-cost-out').value);
        var margin = parseNum(form.querySelector('.js-margin') && form.querySelector('.js-margin').value);
        var sellInEl = form.querySelector('.js-sell-in');
        var sellCacheEl = form.querySelector('.js-sell-cache');
        var sellOutEl = form.querySelector('.js-sell-out');
        if (!sellInEl || !sellCacheEl || !sellOutEl) return;

        if (![costIn, costCache, costOut, margin].every(isFinite) || margin < 0 || margin >= 100) {
            sellInEl.textContent = '—';
            sellCacheEl.textContent = '—';
            sellOutEl.textContent = '—';
            return;
        }

        var divisor = 1 - (margin / 100);
        sellInEl.textContent = formatNum(costIn / divisor);
        sellCacheEl.textContent = formatNum(costCache / divisor);
        sellOutEl.textContent = formatNum(costOut / divisor);
    }

    document.querySelectorAll('.js-price-form').forEach(function (form) {
        form.addEventListener('input', function () { recalc(form); });
        recalc(form);
    });
})();
</script>
@endsection
