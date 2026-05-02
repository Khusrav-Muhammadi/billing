@extends('layouts.app')

@section('title')
    Скидки (внедрение)
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Скидки — потолок скидки на внедрение</h4>

        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCap">
            Создать / обновить
        </button>

        <div class="table-responsive mt-3">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Валюта</th>
                    <th>Тип</th>
                    <th>Потолок (%)</th>
                    <th>Активный</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($caps as $cap)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $cap->currency_code ? strtoupper($cap->currency_code) : '—' }}</td>
                        <td>{{ $cap->period_type === 'months_12' ? '12 месяцев' : 'Стандартная' }}</td>
                        <td>{{ $cap->max_percent }}</td>
                        <td>{{ $cap->is_active ? 'Да' : 'Нет' }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#editCap{{ $cap->id }}">
                                <i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i>
                            </a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#deleteCap{{ $cap->id }}">
                                <i style="color:red; font-size: 30px" class="mdi mdi-delete"></i>
                            </a>
                        </td>
                    </tr>

                    <div class="modal fade" id="editCap{{ $cap->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('implementation-deals.update', $cap->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Изменение скидки</h5>
                                    </div>
                                    <div class="modal-body">
                                        @if(\Illuminate\Support\Facades\Schema::hasColumn('implementation_discount_caps', 'currency_code'))
                                            <div class="form-group mb-2">
                                                <label>Валюта</label>
                                                @if(!empty($currencies))
                                                    <select name="currency_code" class="form-control" required>
                                                        @foreach(($currencies ?? []) as $currency)
                                                            <option value="{{ $currency }}" {{ strtoupper((string) $cap->currency_code) === $currency ? 'selected' : '' }}>
                                                                {{ $currency }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" class="form-control" name="currency_code" value="{{ strtoupper((string) $cap->currency_code) }}" placeholder="UZS" required>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="form-group mb-2">
                                            <label>Тип</label>
                                            <select name="period_type" class="form-control">
                                                <option value="standard" {{ $cap->period_type === 'standard' ? 'selected' : '' }}>Стандартная</option>
                                                <option value="months_12" {{ $cap->period_type === 'months_12' ? 'selected' : '' }}>12 месяцев</option>
                                            </select>
                                        </div>

                                        <div class="form-group mb-2">
                                            <label>Потолок скидки (%)</label>
                                            <input type="number" class="form-control" name="max_percent" value="{{ $cap->max_percent }}" min="0" max="100" step="0.01">
                                        </div>

                                        <div class="form-group mb-2">
                                            <label>Активный</label>
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" class="form-check-inline custom-checkbox" name="is_active" value="1"
                                                   {{ $cap->is_active ? 'checked' : '' }} style="width: 20px; height: 20px">
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

                    <div class="modal fade" id="deleteCap{{ $cap->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('implementation-deals.destroy', $cap->id) }}" method="POST">
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

    <div class="modal fade" id="createCap" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('implementation-deals.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Создание / обновление потолка</h5>
                    </div>
                    <div class="modal-body">
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('implementation_discount_caps', 'currency_code'))
                            <div class="form-group mb-2">
                                <label>Валюта</label>
                                @if(!empty($currencies))
                                    <select name="currency_code" class="form-control" required>
                                        @foreach(($currencies ?? []) as $currency)
                                            <option value="{{ $currency }}">{{ $currency }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" class="form-control" name="currency_code" placeholder="UZS" required>
                                @endif
                            </div>
                        @endif

                        <div class="form-group mb-2">
                            <label>Тип</label>
                            <select name="period_type" class="form-control">
                                <option value="standard">Стандартная</option>
                                <option value="months_12">12 месяцев</option>
                            </select>
                        </div>

                        <div class="form-group mb-2">
                            <label>Потолок скидки (%)</label>
                            <input type="number" class="form-control" name="max_percent" min="0" max="100" step="0.01" placeholder="0">
                        </div>

                        <div class="form-group mb-2">
                            <label>Активный</label>
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="form-check-inline custom-checkbox" name="is_active" value="1" checked style="width: 20px; height: 20px">
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
