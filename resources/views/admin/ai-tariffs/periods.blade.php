@extends('layouts.app')

@section('title') Периоды и скидки: {{ $aiTariff->name }} @endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="card-title mb-0">Периоды и скидки: {{ $aiTariff->name }}</h4>

        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('ai-tariff.index') }}" class="btn btn-light">Назад</a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPeriodModal">
                Добавить период / скидку
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>№</th>
                <th>Месяцев</th>
                <th>Скидка %</th>
                <th>Итого</th>
                <th>Действует с</th>
                <th>Действует по</th>
                <th>Добавил</th>
                <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            @forelse($periods as $period)
                @php $isActive = is_null($period->valid_to); @endphp
                <tr class="{{ $isActive ? '' : 'text-muted' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $period->months }}</strong> мес.</td>
                    <td>
                        @if($period->discount_percent > 0)
                            <span class="badge bg-warning text-dark">{{ $period->discount_percent }}%</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($period->price_total > 0)
                            {{ number_format($period->price_total, 2) }}
                            @if($currentPrice)
                                {{ $currentPrice->currency?->name }}
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        @if($isActive && $period->discount_percent == 0 && $period->price_total == 0)
                            <small class="text-muted">(без скидки)</small>
                        @endif
                    </td>
                    <td>{{ $period->valid_from ? $period->valid_from->format('d.m.Y') : '—' }}</td>
                    <td>
                        @if($isActive)
                            <span class="badge bg-success">Текущая</span>
                        @else
                            {{ $period->valid_to ? $period->valid_to->format('d.m.Y') : '—' }}
                        @endif
                    </td>
                    <td>{{ $period->creator?->name ?? '—' }}</td>
                    <td>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editPeriod{{ $period->id }}">
                            <i class="mdi mdi-pencil-box-outline" style="font-size: 24px"></i>
                        </a>
                        @if($isActive)
                            <a href="#" data-bs-toggle="modal" data-bs-target="#deletePeriod{{ $period->id }}">
                                <i style="color:red; font-size: 24px" class="mdi mdi-delete"></i>
                            </a>
                        @endif
                    </td>
                </tr>

                {{-- Модал редактирования --}}
                <div class="modal fade" id="editPeriod{{ $period->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-tariff.periods.update', $period) }}" method="POST" class="period-edit-form">
                            @csrf @method('PATCH')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Изменить период / скидку</h5>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label>Количество месяцев <span class="text-danger">*</span></label>
                                        <input type="number" name="months" class="form-control period-months"
                                               min="1" max="120" value="{{ $period->months }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Скидка % <span class="text-danger">*</span></label>
                                        <input type="number" name="discount_percent" class="form-control period-discount"
                                               min="0" max="99.99" step="0.01" value="{{ $period->discount_percent }}" required>
                                        <small class="text-muted">0 — без скидки</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Итоговая цена</label>
                                        <input type="text" name="price_total" class="form-control period-price-total"
                                               value="{{ $period->price_total }}" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Действует с <span class="text-danger">*</span></label>
                                        <input type="date" name="valid_from" class="form-control"
                                               value="{{ $period->valid_from ? $period->valid_from->format('Y-m-d') : '' }}" required>
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

                @if($isActive)
                    {{-- Модал удаления активного периода --}}
                    <div class="modal fade" id="deletePeriod{{ $period->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('ai-tariff.periods.destroy', $period) }}" method="POST">
                                @csrf @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Удалить период</h5>
                                    </div>
                                    <div class="modal-body">
                                        Удалить период <strong>{{ $period->months }} мес.</strong> (скидка {{ $period->discount_percent }}%)?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-danger">Удалить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Периоды не добавлены</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Модал добавления периода --}}
<div class="modal fade" id="addPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('ai-tariff.periods.store', $aiTariff) }}" method="POST" id="periodForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить период / скидку — {{ $aiTariff->name }}</h5>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Количество месяцев <span class="text-danger">*</span></label>
                        <input type="number" name="months" id="periodMonths" class="form-control @error('months') is-invalid @enderror"
                               min="1" max="120" value="{{ old('months') }}" required
                               placeholder="например: 1, 3, 6, 12">
                        @error('months')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Скидка % <span class="text-danger">*</span></label>
                        <input type="number" name="discount_percent" id="periodDiscount" class="form-control @error('discount_percent') is-invalid @enderror"
                               min="0" max="99.99" step="0.01" value="{{ old('discount_percent', 0) }}" required>
                        <small class="text-muted">0 — без скидки</small>
                        @error('discount_percent')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Итоговая цена</label>
                        <input type="text" name="price_total" id="periodPriceTotal" class="form-control @error('price_total') is-invalid @enderror"
                               value="{{ old('price_total', 0) }}" readonly>

                        @error('price_total')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Действует с <span class="text-danger">*</span></label>
                        <input type="date" name="valid_from" class="form-control @error('valid_from') is-invalid @enderror"
                               value="{{ old('valid_from', now()->format('Y-m-d')) }}" required>
                        @error('valid_from')<span class="text-danger">{{ $message }}</span>@enderror
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
    const basePrice = {{ $currentPrice ? (float) $currentPrice->price_monthly : 0 }};

    function recalcForm(form) {
        const monthsInput = form.querySelector('.period-months, #periodMonths');
        const discountInput = form.querySelector('.period-discount, #periodDiscount');
        const totalInput = form.querySelector('.period-price-total, #periodPriceTotal');
        if (!monthsInput || !discountInput || !totalInput) return;

        const months = parseFloat(monthsInput.value) || 0;
        const discount = parseFloat(discountInput.value) || 0;
        if (basePrice > 0 && months > 0) {
            totalInput.value = (basePrice * months * (1 - discount / 100)).toFixed(2);
        }
    }

    const addForm = document.getElementById('periodForm');
    if (addForm) {
        addForm.querySelectorAll('#periodMonths, #periodDiscount').forEach(function (el) {
            el.addEventListener('input', function () { recalcForm(addForm); });
        });
        recalcForm(addForm);
    }

    document.querySelectorAll('.period-edit-form').forEach(function (form) {
        form.querySelectorAll('.period-months, .period-discount').forEach(function (el) {
            el.addEventListener('input', function () { recalcForm(form); });
        });
    });
});
</script>
@endsection
