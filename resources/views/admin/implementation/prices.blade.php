@extends('layouts.app')

@section('title')
    Внедрение
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Внедрение — цены по услуге и валюте</h4>

        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createImplementationPrice">
            Добавить
        </button>

        <div class="table-responsive mt-3">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Услуга</th>
                    <th>Валюта</th>
                    <th>Сумма</th>
                    <th>С</th>
                    <th>До</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($prices as $price)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $price->tariff?->name ?? '—' }}</td>
                        <td>{{ $price->currency?->symbol_code ?? '—' }}</td>
                        <td>{{ $price->sum }}</td>
                        <td>{{ $price->start_date }}</td>
                        <td>{{ $price->date }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#editImplementationPrice{{ $price->id }}">
                                <i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i>
                            </a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#deleteImplementationPrice{{ $price->id }}">
                                <i style="color:red; font-size: 30px" class="mdi mdi-delete"></i>
                            </a>
                        </td>
                    </tr>

                    <div class="modal fade" id="editImplementationPrice{{ $price->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('implementation-prices.update', $price->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Изменение цены внедрения</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group mb-2">
                                            <label>Услуга</label>
                                            <select name="tariff_id" class="form-control">
                                                @foreach($tariffs as $tariff)
                                                    <option value="{{ $tariff->id }}" {{ $price->tariff_id == $tariff->id ? 'selected' : '' }}>
                                                        {{ $tariff->name }}
                                                        @if($tariff->is_tariff)
                                                            (тариф)
                                                        @elseif($tariff->is_extra_user)
                                                            (доп. пользователь)
                                                        @else
                                                            (услуга)
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-group mb-2">
                                            <label>Валюта</label>
                                            <select name="currency_id" class="form-control">
                                                @foreach($currencies as $currency)
                                                    <option value="{{ $currency->id }}" {{ $price->currency_id == $currency->id ? 'selected' : '' }}>
                                                        {{ $currency->symbol_code }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-group mb-2">
                                            <label>Сумма</label>
                                            <input type="text" class="form-control js-money-input" name="sum" value="{{ $price->sum }}">
                                        </div>

                                        <div class="form-group mb-2">
                                            <label>С</label>
                                            <input type="date" class="form-control" name="start_date" value="{{ $price->start_date }}">
                                        </div>

                                        <div class="form-group mb-2">
                                            <label>До</label>
                                            <input type="date" class="form-control" name="date" value="{{ $price->date }}">
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

                    <div class="modal fade" id="deleteImplementationPrice{{ $price->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('implementation-prices.destroy', $price->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Удаление</h5>
                                    </div>
                                    <div class="modal-body">
                                        Вы уверены что хотите удалить?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-danger">Удалить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="createImplementationPrice" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('implementation-prices.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить цену внедрения</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-2">
                            <label>Услуга</label>
                            <select name="tariff_id" class="form-control">
                                @foreach($tariffs as $tariff)
                                    <option value="{{ $tariff->id }}">
                                        {{ $tariff->name }}
                                        @if($tariff->is_tariff)
                                            (тариф)
                                        @elseif($tariff->is_extra_user)
                                            (доп. пользователь)
                                        @else
                                            (услуга)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-2">
                            <label>Валюта</label>
                            <select name="currency_id" class="form-control">
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}">{{ $currency->symbol_code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-2">
                            <label>Сумма</label>
                            <input type="text" class="form-control js-money-input" name="sum" placeholder="0">
                        </div>

                        <div class="form-group mb-2">
                            <label>С</label>
                            <input type="date" class="form-control" name="start_date" value="{{ now()->format('Y-m-d') }}">
                        </div>

                        <div class="form-group mb-2">
                            <label>До</label>
                            <input type="date" class="form-control" name="date">
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
            const parseNumberInput = (value) => {
                if (value === null || value === undefined) return null;
                const cleaned = String(value)
                    .replace(/\s/g, '')
                    .replace(/\u00A0/g, '')
                    .replace(/,/g, '.')
                    .replace(/[^0-9.]/g, '');
                if (!cleaned) return null;
                const num = Number(cleaned);
                return Number.isFinite(num) ? num : null;
            };

            const formatNumberInput = (num) => {
                const value = Number(num);
                if (!Number.isFinite(value)) return '';
                return value.toLocaleString('ru-RU', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 4,
                    useGrouping: true
                });
            };

            document.addEventListener('blur', (e) => {
                const el = e.target;
                if (!el || !el.classList || !el.classList.contains('js-money-input')) return;
                const parsed = parseNumberInput(el.value);
                if (parsed === null) return;
                el.value = formatNumberInput(parsed);
            }, true);
        })();
    </script>
@endsection
