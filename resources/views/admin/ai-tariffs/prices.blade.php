@extends('layouts.app')

@section('title') Цены тарифа: {{ $aiTariff->name }} @endsection

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
            <h4 class="card-title mb-0">Цены тарифа: {{ $aiTariff->name }}</h4>
            @if($aiTariff->model)
                <div class="text-muted" style="font-size: 13px;">Модель: <code>{{ $aiTariff->model }}</code></div>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('ai-tariff.index') }}" class="btn btn-light">Назад</a>
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
                <th>Цена / мес</th>
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
                    <td>
                        <strong>{{ number_format($price->price_monthly, 2) }}</strong>
                        @if($isCurrent)
                            <span class="badge bg-success ms-1">Текущая</span>
                        @endif
                    </td>
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

                {{-- Модал редактирования --}}
                <div class="modal fade" id="editPrice{{ $price->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-tariff.prices.update', $price) }}" method="POST">
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
                                        <label>Цена в месяц <span class="text-danger">*</span></label>
                                        <input type="text" inputmode="decimal" class="form-control" name="price_monthly" value="{{ $price->price_monthly }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Дата начала <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="start_date" value="{{ $price->start_date->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Дата завершения</label>
                                        <input type="date" class="form-control" name="end_date"
                                               value="{{ ($price->end_date && $price->end_date->format('Y-m-d') !== '9999-12-31') ? $price->end_date->format('Y-m-d') : '' }}">
                                        <small class="text-muted"> </small>
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

                {{-- Модал удаления --}}
                <div class="modal fade" id="deletePrice{{ $price->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-tariff.prices.destroy', $price) }}" method="POST">
                            @csrf @method('DELETE')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Удалить цену</h5>
                                </div>
                                <div class="modal-body">
                                    Удалить цену <strong>{{ number_format($price->price_monthly, 2) }} {{ $price->currency?->name }}</strong>?
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
                    <td colspan="7" class="text-center text-muted py-4">Цены не добавлены</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Модал создания --}}
<div class="modal fade" id="createPriceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('ai-tariff.prices.store', $aiTariff) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить цену — {{ $aiTariff->name }}</h5>
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
                        <label>Цена в месяц <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control @error('price_monthly') is-invalid @enderror"
                               name="price_monthly" value="{{ old('price_monthly') }}" required>
                        @error('price_monthly')<span class="text-danger">{{ $message }}</span>@enderror
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
                        <small class="text-muted"> </small>
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
