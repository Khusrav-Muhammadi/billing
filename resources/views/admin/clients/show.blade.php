@extends('layouts.app')

@section('title')
    Клиент - {{ $client->name }}
@endsection

@section('content')
    <form method="POST" action="{{ route('client.update', $client->id) }}">
        @csrf
        @method('PATCH')

        <div class="mb-3 d-flex justify-content-start gap-5">
            <a href="{{ route('client.index') }}" class="btn btn-outline-danger">Назад</a>

            <button type="submit" style="margin-left: 10px" class="btn btn-outline-primary">Сохранить</button>

            <a href="#" style="margin-left: 10px" data-bs-toggle="modal" data-bs-target="#history" class="btn btn-outline-dark">
                <i class="mdi mdi-history" style="font-size: 30px"></i>
            </a>

            <a href="#" style="margin-left: 10px" data-bs-toggle="modal" data-bs-target="#activation"
               class="btn btn-outline-{{ $client->is_active ? 'success' : 'danger' }}">
                <i class="mdi mdi-power" style="font-size: 30px"></i>
            </a>
            <a href="#" style="margin-left: 10px" data-bs-toggle="modal" data-bs-target="#deleteAll"
               class="btn btn-outline-danger">
                <i class="mdi mdi-trash-can" style="font-size: 30px"></i>
            </a>
        </div>

        <div class="card mb-3 p-3">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="name">ФИО</label>
                    <input type="text" class="form-control" name="name" value="{{ $client->name }}">
                </div>
                <div class="col-md-4">
                    <label for="phone">Телефон</label>
                    <input type="text" class="form-control" name="phone" value="{{ $client->phone }}">
                </div>
                <div class="col-md-4">
                    <label for="email">Почта</label>
                    <input type="email" class="form-control" name="email" value="{{ $client->email }}">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="sub_domain">Поддомен</label>
                    <input type="text" class="form-control" name="sub_domain" value="{{ $client->sub_domain }}">
                </div>
                <div class="col-md-4">
                    <label for="tariff_id">Тариф</label>
                    <select class="form-control" name="tariff_id">
                        @foreach($tariffs as $tariff)
                            <option value="{{ $tariff->id }}" {{ $client->tariffPrice->tariff_id == $tariff->id ? 'selected' : '' }}>
                                {{ $tariff->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="partner_id">Партнер</label>
                    <select class="form-control @error('partner_id') is-invalid @enderror" name="partner_id">
                        <option value="">Выберите партнера</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" {{ $client->partner_id == $partner->id ? 'selected' : '' }}>
                                {{ $partner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="country_id">Страна</label>
                    <select class="form-control" name="country_id">
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ $client->country_id == $country->id ? 'selected' : '' }}>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Дата создания</label>
                    <div class="form-control bg-light">
                        {{ \Carbon\Carbon::parse($client->created_at)->format('d.m.Y') }}
                    </div>
                </div>
                <div class="col-md-4">
                    <label>Дата окончания доступа</label>
                    <div class="form-control {{ $expirationDate && $expirationDate->isPast() ? 'bg-danger text-white' : 'bg-light' }}">
                        {{ $expirationDate ? $expirationDate->format('d.m.Y') : 'Не определено' }}
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                @if($client->is_demo)
                    <div style="margin-left: 20px" class="col-md-4 d-flex align-items-center">
                        <label for="is_demo" class="me-2">Демо версия:</label>
                        <input type="checkbox" name="is_demo" class="form-check-input"
                            {{ $client->is_demo ? 'checked' : '' }}>
                    </div>
                @endif

                @if($client->nfr)
                    <div class="col-md-4 d-flex align-items-center">
                        <label for="nfr" class="me-2">НФР:</label>
                        <input type="checkbox" class="form-check-input" {{ $client->nfr ? 'checked' : '' }}>
                    </div>
                @endif
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <div style="margin-bottom: 10px">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach($errors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <div class="d-flex">
            <div class="card table-container flex-fill mr-3">
                <div class="d-flex justify-content-between align-items-center mb-3 ">
                    @if($client->balance >= $client->tariff?->price && $client->is_active || $client->is_demo || $client->nfr)
                        <a href="" data-bs-toggle="modal" data-bs-target="#createOrganization" type="button"
                           class="btn btn-outline-primary ml-2 mt-2">Создать</a>
                    @endif
                    <h4 class="card-title text-center flex-grow-1 mb-0 mt-3">Организации</h4>
                </div>

                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Имя</th>
                        <th>Телефон</th>
                        <th>Активный</th>
                        <th>Действие</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($organizations as $organization)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $organization->name }}</td>
                            <td>{{ $organization->phone }}</td>
                            <td>
                                <input type="checkbox" class="form-control" name="has_access" style="width: 30px"
                                       data-bs-toggle="modal"
                                       data-bs-target="#organizationActivation{{$organization->id}}" {{ $organization->has_access ? 'checked' : '' }}
                                    {{ $client->is_active ? '' : 'disabled' }}
                                >
                            </td>
                            <td>
                                <a href="{{ route('organization.show', $organization->id) }}">
                                    <i class="mdi mdi-eye" style="font-size: 30px"></i></i>
                                </a>
                                <a href="" data-bs-toggle="modal"
                                   data-bs-target="#editOrganization{{$organization->id}}">
                                    <i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i>
                                </a>
                            </td>
                        </tr>

                        <div class="modal fade" id="organizationActivation{{$organization->id}}" tabindex="-1"
                             aria-labelledby="exampleModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                @if(!$organization->has_access)
                                    <form action="{{ route('organization.access', $organization->id) }}" method="POST">
                                        @endif
                                        @csrf

                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"
                                                    id="exampleModalLabel"> {{ $organization->has_access ? 'Деактивация' : 'Активация' }}
                                                    организации</h5>
                                            </div>
                                            <div class="modal-body">
                                                Вы уверены что
                                                хотите {{ $organization->has_access ? 'деактивировать' : 'активировать' }}
                                                эту организацию?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    Отмена
                                                </button>
                                                <button type="submit"
                                                        class="btn btn-{{ $organization->has_access ? 'danger' : 'success' }}"
                                                        @if($organization->has_access) data-bs-toggle="modal"
                                                        data-bs-target="#organization_reject_cause" @endif>{{ $organization->has_access ? 'Деактивировать' : 'Активировать' }}</button>
                                            </div>
                                        </div>
                                    </form>
                            </div>
                        </div>

                        <div class="modal fade" id="editOrganization{{$organization->id}}" tabindex="-1"
                             aria-labelledby="exampleModalLabel"
                             aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('organization.update', $organization->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLabel">Обновить организацию</h5>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label for="name">Наименование</label>
                                                <input type="text" class="form-control" name="name"
                                                       value="{{ $organization->name }}">
                                            </div>

                                            <div class="form-group">
                                                <label for="phone">Телефон</label>
                                                <input type="number" class="form-control" name="phone"
                                                       value="{{ $organization->phone }}">
                                            </div>


                                            <div class="form-group">
                                                <label for="sub_domain">Адрес</label>
                                                <textarea name="address" cols="30" rows="10"
                                                          class="form-control">{{ $organization->address }}</textarea>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                Отмена
                                            </button>
                                            <button type="submit" class="btn btn-primary">Сохранить</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="modal fade" id="addPack{{$organization->id}}" tabindex="-1"
                             aria-labelledby="exampleModalLabel"
                             aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('organization.addPack', $organization->id) }}">
                                    @csrf
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLabel">Добавить пакет на
                                                организацию {{ $organization->name }}</h5>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label for="tariff_id">Пакеты</label>
                                                <select class="form-control form-control" name="pack_id" required>
                                                    @foreach($packs as $pack)
                                                        <option value="{{ $pack->id }}">{{ $pack->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="phone">Дата</label>
                                                <input type="date" class="form-control" name="date" required>
                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Отмена
                                                </button>
                                                <button type="submit" class="btn btn-primary">Сохранить</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card table-container flex-fill ml-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a href="" data-bs-toggle="modal" data-bs-target="#createTransaction" type="button"
                       class="btn btn-outline-primary m-2">Пополнить баланс</a>
                    <h4 class="card-title m-2 text-center flex-grow-1 mb-0">Транзакции</h4>
                </div>
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Дата</th>
                        <th>Организация</th>
                        <th>Тариф</th>
                        <th>Скидка</th>
                        <th>Сумма</th>
                        <th>Тип</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($transactions as $transaction)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $transaction->created_at }}</td>
                            <td>{{ $transaction->organization?->name }}</td>
                            <td>{{ $transaction->tariff?->name }}</td>
                            <td>{{ $transaction->sale?->amount }}</td>
                            <td>{{ $transaction->sum }}</td>
                            <td style="color: {{ $transaction->type == 'Снятие' ? 'red' : 'green' }}">{{ $transaction->type }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="mb-3 ml-2 mt-3 text-center">
                    {{$transactions->links()}}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createOrganization" tabindex="-1" aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('organization.store', $client->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создать организацию</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Наименование <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="Введите название">
                        </div>

                        <div class="form-group" id="phone-container">
                            <label for="phone">Телефон <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                   id="phone" name="phone" placeholder="Телефон"
                                   value="{{ $partnerRequest->phone ?? old('phone') }}" required>
                            @error('phone')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

{{--                        <div class="form-group">--}}
{{--                            <label for="sub_domain">Адрес <span class="text-danger">*</span></label>--}}
{{--                            <textarea name="address" cols="30" rows="10" placeholder="Адрес"--}}
{{--                                      class="form-control"></textarea>--}}
{{--                        </div>--}}
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="createTransaction" tabindex="-1" aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('client.createTransaction', $client->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Пополнить баланс</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="date">Дата</label>
                            <input type="date" class="form-control" name="date" id="date">
                        </div>

                        <div class="form-group">
                            <label for="sum">Сумма</label>
                            <input type="number" class="form-control" name="sum" id="sum" placeholder="Сумма">
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-semibold mb-3">Период подписки</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <input class="btn-check" type="radio" name="period" id="period6" value="6" checked>
                                    <label class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center" for="period6">
                                        <span class="fs-4 fw-bold">6</span>
                                        <span class="small text-muted">месяцев</span>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input class="btn-check" type="radio" name="period" id="period12" value="12">
                                    <label class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center" for="period12">
                                        <span class="fs-4 fw-bold">12</span>
                                        <span class="small text-muted">месяцев</span>
                                    </label>
                                </div>
                            </div>
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


    <div class="modal fade" id="activation" tabindex="-1"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            @if(!$client->is_active)
                <form action="{{ route('client.activation', $client->id) }}" method="POST">
                    @endif
                    @csrf

                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"
                                id="exampleModalLabel"> {{ $client->is_active ? 'Деактивация' : 'Активация' }}
                                клиента</h5>
                        </div>
                        <div class="modal-body">
                            Вы уверены что хотите {{ $client->is_active ? 'деактивировать' : 'активировать' }} эти
                            данные?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Отмена
                            </button>
                            <button type="submit"
                                    class="btn btn-{{ $client->is_active ? 'danger' : 'success' }}"
                                    @if($client->is_active) data-bs-toggle="modal"
                                    data-bs-target="#reject_cause" @endif>{{ $client->is_active ? 'Деактивировать' : 'Активировать' }}</button>
                        </div>
                    </div>
                </form>
        </div>
    </div>

    <div class="modal fade" id="deleteAll" tabindex="-1"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
                <form action="{{ route('client.deleteAll', $client->id) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"
                                id="exampleModalLabel"> Полное удаление</h5>
                        </div>
                        <div class="modal-body">
                            Вы уверены что хотите полностью удалить  данные клиента?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Отмена
                            </button>
                            <button type="submit"
                                    class="btn btn-danger"
                                   data-bs-toggle="modal" data-bs-target="#deleteAll">Удалить данные</button>
                        </div>
                    </div>
                </form>
        </div>
    </div>

    <div class="modal fade" id="reject_cause" tabindex="-1" aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('client.activation', $client->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Причина отклонения</h5>
                    </div>
                    <div class="modal-body">
                                        <textarea name="reject_cause" id="" cols="30" rows="5" class="form-control"
                                                  placeholder="Почему вы отклоняете этот запрос?"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена
                        </button>
                        <button type="submit" class="btn btn-primary">Отправить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(isset($organization))
    <div class="modal fade" id="organization_reject_cause" tabindex="-1" aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('organization.access', $organization->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Причина отклонения</h5>
                    </div>
                    <div class="modal-body">
                                        <textarea name="reject_cause" id="" cols="30" rows="5" class="form-control"
                                                  placeholder="Почему вы отклоняете этот запрос?"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена
                        </button>
                        <button type="submit" class="btn btn-primary">Отправить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="modal fade" id="history" tabindex="-1"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"
                        id="exampleModalLabel"> История клиента</h5>
                </div>
                <div class="modal-body">
                    @foreach($client->history as $history)
                        <div style="display: flex; justify-content: space-between;">
                            <h4>{{ $history->status }}</h4>
                            <span>
                                <strong>{{ $history->user?->name }}</strong> <i
                                    style="font-size: 14px">{{ $history->created_at->format('d.m.Y H:i') }}</i>
                            </span>
                        </div>
                        <div class="ml-3" style="font-size: 14px">
                            @foreach ($history->changes as $change)
                                @php
                                    $bodyData = json_decode($change->body, true);
                                @endphp

                                @foreach ($bodyData as $key => $value)
                                    @if($key == 'name') Имя: <br>
                                    @elseif($key == 'phone') Телефон: <br>
                                    @elseif($key == 'email') Почта: <br>
                                    @elseif($key == 'client_type') Тип клиента: <br>
                                    @elseif($key == 'tariff') Тариф: <br>
                                    @elseif($key == 'is_active') Доступ: <br>
                                    @elseif($key == 'reject_cause') Причина: <br>
                                    @endif
                                    @if($key == 'is_active')
                                        <p style="margin-left: 20px;"> {{ $value['new_value'] == 0 ? 'Деактивирован' : 'Активирован' }}</p>
                                    @elseif($key = 'reject_cause')
                                        <p style="margin-left: 20px;">{{ $value['new_value'] ?? 'N/A' }}</p>
                                    @else
                                        <p style="margin-left: 20px;">{{ $value['previous_value'] ?? 'N/A' }}
                                            ==> {{ $value['new_value'] ?? 'N/A' }}</p>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                        <hr>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        const dateInput = document.getElementById('date');
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        dateInput.value = `${year}-${month}-${day}`;
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Находим поле телефона
            const phoneInput = document.getElementById("phone");

            // Инициализируем международный телефонный ввод
            const iti = window.intlTelInput(phoneInput, {
                initialCountry: "tj",
                separateDialCode: true,
                preferredCountries: ["ru", "us", "tj", "kz", "ua"],
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
            });

            // Обрабатываем изменение флага страны
            phoneInput.addEventListener("countrychange", function() {
                adjustPhoneInputWidth();
            });

            // Ограничиваем ввод только цифрами и управляем максимальной длиной
            phoneInput.addEventListener("input", function(e) {
                // Удаляем все нецифровые символы
                const inputValue = e.target.value.replace(/\D/g, '');

                // Ограничиваем максимальную длину до 15 цифр (международный стандарт)
                const limitedValue = inputValue.substring(0, 10);

                // Устанавливаем отфильтрованное значение обратно в поле
                e.target.value = limitedValue;
            });

            // Добавляем валидацию при потере фокуса
            phoneInput.addEventListener("blur", function() {
                if (phoneInput.value.trim() !== '') {
                    if (!iti.isValidNumber()) {
                        phoneInput.classList.add('is-invalid');

                        // Находим или создаем элемент для сообщения об ошибке
                        let errorElement = phoneInput.nextElementSibling;
                        if (!errorElement || !errorElement.classList.contains('text-danger')) {
                            errorElement = document.createElement('span');
                            errorElement.classList.add('text-danger');
                            phoneInput.parentNode.insertBefore(errorElement, phoneInput.nextSibling);
                        }

                    } else {
                        phoneInput.classList.remove('is-invalid');

                        // Удаляем сообщение об ошибке, если оно есть
                        const errorElement = phoneInput.nextElementSibling;
                        if (errorElement && errorElement.classList.contains('text-danger')) {
                            errorElement.textContent = '';
                        }
                    }
                }
            });

            // Функция для регулировки ширины поля ввода телефона
            function adjustPhoneInputWidth() {
                const phoneContainer = phoneInput.closest('.form-group');
                const phoneInputContainer = phoneContainer.querySelector('.iti');
                if (phoneInputContainer) {
                    phoneInputContainer.style.width = '100%';
                }
            }

            // Применяем регулировку ширины при загрузке
            adjustPhoneInputWidth();

            // Обработка отправки формы
            const form = phoneInput.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Проверяем валидность телефона перед отправкой
                    if (!iti.isValidNumber()) {
                        e.preventDefault();
                        phoneInput.classList.add('is-invalid');

                        // Находим или создаем элемент для сообщения об ошибке
                        let errorElement = phoneInput.nextElementSibling;
                        if (!errorElement || !errorElement.classList.contains('text-danger')) {
                            errorElement = document.createElement('span');
                            errorElement.classList.add('text-danger');
                            phoneInput.parentNode.insertBefore(errorElement, phoneInput.nextSibling);
                        }

                        errorElement.textContent = 'Пожалуйста, введите корректный номер телефона';
                        phoneInput.focus();
                        return false;
                    }

                    // Получаем полный номер телефона с кодом страны
                    const fullNumber = iti.getNumber();

                    // Устанавливаем полный номер обратно в поле ввода
                    phoneInput.value = fullNumber;
                });
            }
        });
    </script>
@endsection
