@extends('layouts.app')

@section('title')
    Прайслист
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Прайслист</h4>
        <div class="table-responsive">
            <a href="#" data-bs-toggle="modal" data-bs-target="#create" type="button" class="btn btn-primary">Добавить</a>
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Дата начала</th>
                    <th>Дата завершения</th>
                    <th>Валюта</th>
                    <th>Услуга</th>
                    <th>Организация</th>
                    <th>Сумма</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($priceLists as $price)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $price->start_date ?? '—' }}</td>
                        <td>{{ ($price->date ?? '') === '9999-12-31' ? '—' : ($price->date ?? '—') }}</td>
                        <td>{{ $price->currency?->name }}</td>
                        <td>{{ $price->tariff?->name }}</td>
                        <td>{{ $price->organization?->name }}</td>
                        <td>{{ $price->sum }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $price->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $price->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $price->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('price_list.update', $price ->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение прайслиста</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="tariff_id">Услуга <span class="text-danger">*</span></label>
                                            <select class="form-control" name="tariff_id" required>
                                                <option value="">Выберите услугу</option>
                                                @foreach($tariffs as $tariff)
                                                    <option value="{{ $tariff->id }}" {{ $price->tariff_id == $tariff->id ? 'selected' : '' }}>
                                                        {{ $tariff->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Организация</label>
                                            <input type="text" class="form-control mb-2 js-organization-search" placeholder="Поиск организации...">
                                            <select class="form-control js-organization-select" name="organization_id">
                                                <option value="">Без организации (общая цена)</option>
                                                @foreach($organizations as $organization)
                                                    <option value="{{ $organization->id }}" {{ $price->organization_id == $organization->id ? 'selected' : '' }}>
                                                        {{ $organization->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Дата начала</label>
                                            <input type="date" class="form-control" name="start_date" value="{{ $price->start_date }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Дата завершения</label>
                                            <input type="date" class="form-control" name="date"
                                                   value="{{ ($price->date ?? '') === '9999-12-31' ? '' : $price->date }}">
                                        </div>

                                        <div class="form-group">
                                            <label for="name">Сумма</label>
                                            <input type="text" inputmode="decimal" class="form-control" name="sum" value="{{ $price->sum }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="tariff_id">Валюта <span class="text-danger">*</span></label>
                                            <select class="form-control" name="currency_id" required>
                                                <option value="">Выберите валюту</option>
                                                @foreach($currencies as $currency)
                                                    <option value="{{ $currency->id }}" {{ $price->currency_id == $currency->id ? 'selected' : '' }}>
                                                        {{ $currency->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-primary">Изменить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="modal fade" id="delete{{ $price->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('price_list.delete', $price->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление прайслиста</h5>
                                    </div>
                                    <div class="modal-body">
                                        Вы уверены что хотите удалить эти данные?
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

    <div class="card-body">
        <h4 class="card-title">Диапазоны цен доп. пользователей</h4>
        <div class="table-responsive">
            <a href="#" data-bs-toggle="modal" data-bs-target="#createExtraUserTier" type="button" class="btn btn-primary">Добавить диапазон</a>
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Тариф</th>
                    <th>Организация</th>
                    <th>Валюта</th>
                    <th>От пользователей</th>
                    <th>До пользователей</th>
                    <th>Цена за 1 доп. пользователя</th>
                    <th>Дата начала</th>
                    <th>Дата завершения</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($extraUserPriceTiers as $tier)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $tier->tariff?->name }}</td>
                        <td>{{ $tier->organization?->name ?? 'Общая цена' }}</td>
                        <td>{{ $tier->currency?->name }}</td>
                        <td>{{ $tier->min_total_users }}</td>
                        <td>{{ $tier->max_total_users ?? '∞' }}</td>
                        <td>{{ $tier->unit_price }}</td>
                        <td>{{ optional($tier->start_date)->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ optional($tier->end_date)->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#editExtraUserTier{{ $tier->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#deleteExtraUserTier{{ $tier->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="editExtraUserTier{{ $tier->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('price_list.extra_user_tiers.update', $tier->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Изменение диапазона доп. пользователей</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Тариф <span class="text-danger">*</span></label>
                                            <select class="form-control" name="tariff_id" required>
                                                <option value="">Выберите тариф</option>
                                                @foreach($baseTariffs as $tariff)
                                                    <option value="{{ $tariff->id }}" {{ $tier->tariff_id == $tariff->id ? 'selected' : '' }}>
                                                        {{ $tariff->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Организация</label>
                                            <input type="text" class="form-control mb-2 js-organization-search" placeholder="Поиск организации...">
                                            <select class="form-control js-organization-select" name="organization_id">
                                                <option value="">Без организации (общая цена)</option>
                                                @foreach($organizations as $organization)
                                                    <option value="{{ $organization->id }}" {{ $tier->organization_id == $organization->id ? 'selected' : '' }}>
                                                        {{ $organization->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Валюта <span class="text-danger">*</span></label>
                                            <select class="form-control" name="currency_id" required>
                                                <option value="">Выберите валюту</option>
                                                @foreach($currencies as $currency)
                                                    <option value="{{ $currency->id }}" {{ $tier->currency_id == $currency->id ? 'selected' : '' }}>
                                                        {{ $currency->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>От общего кол-ва пользователей</label>
                                            <input type="number" min="1" class="form-control" name="min_total_users" value="{{ $tier->min_total_users }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label>До общего кол-ва пользователей</label>
                                            <input type="number" min="1" class="form-control" name="max_total_users" value="{{ $tier->max_total_users }}">
                                        </div>
                                        <div class="form-group">
                                            <label>Цена за 1 доп. пользователя</label>
                                            <input type="text" inputmode="decimal" class="form-control" name="unit_price" value="{{ $tier->unit_price }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Дата начала</label>
                                            <input type="date" class="form-control" name="start_date" value="{{ optional($tier->start_date)->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Дата завершения</label>
                                            <input type="date" class="form-control" name="end_date" value="{{ optional($tier->end_date)->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-primary">Изменить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="modal fade" id="deleteExtraUserTier{{ $tier->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('price_list.extra_user_tiers.delete', $tier->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Удаление диапазона</h5>
                                    </div>
                                    <div class="modal-body">
                                        Вы уверены что хотите удалить эти данные?
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

    <div class="modal fade" id="createExtraUserTier" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('price_list.extra_user_tiers.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Создание диапазона доп. пользователей</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Тариф <span class="text-danger">*</span></label>
                            <select class="form-control" name="tariff_id" required>
                                <option value="">Выберите тариф</option>
                                @foreach($baseTariffs as $tariff)
                                    <option value="{{ $tariff->id }}" {{ old('tariff_id') == $tariff->id ? 'selected' : '' }}>
                                        {{ $tariff->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Организация</label>
                            <input type="text" class="form-control mb-2 js-organization-search" placeholder="Поиск организации...">
                            <select class="form-control js-organization-select" name="organization_id">
                                <option value="">Без организации (общая цена)</option>
                                @foreach($organizations as $organization)
                                    <option value="{{ $organization->id }}" {{ old('organization_id') == $organization->id ? 'selected' : '' }}>
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Валюта <span class="text-danger">*</span></label>
                            <select class="form-control" name="currency_id" required>
                                <option value="">Выберите валюту</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}" {{ old('currency_id') == $currency->id ? 'selected' : '' }}>
                                        {{ $currency->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>От общего кол-ва пользователей</label>
                            <input type="number" min="1" class="form-control" name="min_total_users" value="{{ old('min_total_users', 1) }}" required>
                        </div>
                        <div class="form-group">
                            <label>До общего кол-ва пользователей</label>
                            <input type="number" min="1" class="form-control" name="max_total_users" value="{{ old('max_total_users') }}">
                        </div>
                        <div class="form-group">
                            <label>Цена за 1 доп. пользователя</label>
                            <input type="text" inputmode="decimal" class="form-control" name="unit_price" value="{{ old('unit_price') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Дата начала</label>
                            <input type="date" class="form-control" name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
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

    <div class="modal fade" id="create" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('price_list.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создание прайслиста</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="tariff_id">Услуга <span class="text-danger">*</span></label>
                            <select class="form-control form-control @error('tariff_id') is-invalid @enderror"
                                    name="tariff_id" required>
                                <option value="">Выберите услугу</option>
                                @foreach($tariffs as $tariff)
                                    <option
                                        value="{{ $tariff->id }}" {{ old('tariff_id') == $tariff->id ? 'selected' : '' }}>
                                        {{ $tariff->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('tariff_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="organization_id">Организация</label>
                            <input type="text" class="form-control mb-2 js-organization-search" placeholder="Поиск организации...">
                            <select class="form-control js-organization-select @error('organization_id') is-invalid @enderror"
                                    name="organization_id">
                                <option value="">Без организации (общая цена)</option>
                                @foreach($organizations as $organization)
                                    <option
                                        value="{{ $organization->id }}" {{ old('organization_id') == $organization->id ? 'selected' : '' }}>
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('organization_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="name">Дата начала</label>
                            <input type="date" class="form-control" name="start_date"
                                   value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="form-group">
                            <label for="name">Дата завершения</label>
                            <input type="date" class="form-control" name="date" value="{{ old('date') }}">
                        </div>
                        <div class="form-group">
                            <label for="name">Сумма</label>
                            <input type="text" inputmode="decimal" class="form-control" name="sum" value="{{ old('sum') }}">
                        </div>
                        <div class="form-group">
                            <label for="tariff_id">Валюта <span class="text-danger">*</span></label>
                            <select class="form-control form-control @error('currency_id') is-invalid @enderror"
                                    name="currency_id" required>
                                <option value="">Выберите валюту</option>
                                @foreach($currencies as $currency)
                                    <option
                                        value="{{ $currency->id }}" {{ old('currency_id') == $currency->id ? 'selected' : '' }}>
                                        {{ $currency->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-organization-search').forEach((input) => {
                input.addEventListener('input', () => {
                    const modalBody = input.closest('.modal-body') || document;
                    const select = modalBody.querySelector('.js-organization-select');
                    if (!select) return;

                    const term = (input.value || '').trim().toLowerCase();
                    Array.from(select.options).forEach((opt) => {
                        if (!opt.value) return; // keep placeholder
                        const text = (opt.textContent || '').toLowerCase();
                        opt.hidden = term ? !text.includes(term) : false;
                    });
                });
            });
        });
    </script>

@endsection
