@extends('layouts.app')

@section('title')
    Клиент - {{ $client->name }}
@endsection

@section('content')
    <form method="POST" action="{{ route('client.update', $client->id) }}">
        <a href="{{ route('client.index') }}" class="btn btn-outline-danger mb-2">Назад</a>
        <button type="submit" class="btn btn-outline-primary mb-2"> Сохранить</button>
        <a href="" data-bs-toggle="modal" data-bs-target="#history" type="button"
           class="btn btn-outline-dark mb-2 ml-5">
            <i class="mdi mdi-history" style="font-size: 30px"></i>
        </a>
        <a href="" data-bs-toggle="modal" data-bs-target="#activation" type="button"
           class="btn btn-outline-{{ $client->is_active ? 'success' : 'danger' }} mb-2 ml-5">
            <i class="mdi mdi-power" style="font-size: 30px"></i>
        </a>
        <div class="card mb-3">
            @csrf
            @method('PATCH')
            <div class="row mb-3">
                <div class="col-4 ml-5 mt-3">
                    <label for="name">ФИО</label>
                    <input type="text" class="form-control" name="name" value="{{ $client->name }}">
                </div>
                <div class="col-4 mt-3">
                    <label for="phone">Телефон</label>
                    <input type="text" class="form-control" name="phone" value="{{ $client->phone }}">
                </div>
                <div class="col-3 mt-3">
                    <label for="email">Почта</label>
                    <input type="email" class="form-control" name="email" value="{{ $client->email }}">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-4 ml-5 mt-3">
                    <label for="sub_domain">Поддомен</label>
                    <input type="text" class="form-control" value="{{ $client->sub_domain }}">
                </div>
                <div class="col-4 mt-3">
                    <label for="client_type">Тип клиента</label>
                    <select class="form-control form-control-sm @error('client_type') is-invalid @enderror"
                            name="client_type" required>
                        <option value="">Выберите тип клиента</option>
                        <option
                            value="{{ \App\Enums\ClientType::LegalEntity->value }}" {{ $client->client_type == \App\Enums\ClientType::LegalEntity->value ? 'selected' : '' }}>
                            {{ \App\Enums\ClientType::LegalEntity->value }}
                        </option>
                        <option
                            value="{{ \App\Enums\ClientType::Individual->value }}" {{ $client->client_type == \App\Enums\ClientType::Individual->value ? 'selected' : '' }}>
                            {{ \App\Enums\ClientType::Individual->value }}
                        </option>
                        <option
                            value="{{ \App\Enums\ClientType::Entrepreneur->value }}" {{ $client->client_type == \App\Enums\ClientType::Entrepreneur->value ? 'selected' : '' }}>
                            {{ \App\Enums\ClientType::Entrepreneur->value }}
                        </option>
                    </select>
                </div>
                <div class="col-3 mt-3">
                    <label for="tariff_id">Тариф</label>
                    <select class="form-control form-control-sm" name="tariff_id">
                        @foreach($tariffs as $tariff)
                            <option
                                value="{{ $tariff->id }}" {{ $client->tariff_id == $tariff->id ? 'selected': '' }}>{{ $tariff->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-4 ml-5 mt-3">
                    <label for="contact_person">Контактное лицо</label>
                    <input type="text" class="form-control @error('contact_person') is-invalid @enderror"
                           name="contact_person" value="{{ $client->contact_person }}">
                </div>
                <div class="col-4 mt-3">
                    <label for="business_type_id">Партнер</label>
                    <select class="form-control form-control-sm @error('business_type_id') is-invalid @enderror"
                            name="business_type_id">
                        <option value="">Выберите партнера</option>
                        @foreach($partners as $partner)
                            <option
                                value="{{ $partner->id }}" {{ old('business_type_id') == $partner->id ? 'selected' : '' }}>
                                {{ $partner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-3 mt-3">
                    <label for="sale_id">Скидка</label>
                    <select class="form-control form-control-sm" name="sale_id">
                        @foreach($sales as $sale)
                            <option
                                value="{{ $sale->id }}" {{ $client->sale_id == $sale->id ? 'selected': '' }}>{{ $sale->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row mb-3 ml-4">
                @if($client->is_demo)
                    <div class="col-4 mt-3" style="display: flex; align-items: center;">
                        <label for="is_demo" style="margin-right: 10px;">Демо версия:</label>
                        <input type="checkbox" name="is_demo" class="form-control"
                               style="width: 40px; margin: 0;" {{ $client->is_demo ? 'checked' : '' }}>
                    </div>
                @endif

                @if($client->nfr)
                    <div class="col-4 ml-2 mt-3" style="display: flex; align-items: center;">
                        <label for="is_demo" style="margin-right: 10px;">НФР:</label>
                        <input type="checkbox" class="form-control"
                               style="width: 40px; margin: 0;" {{ $client->nfr ? 'checked' : '' }}>
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
                        <th>Тип бизнеса</th>
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
                            <td>{{ $organization->businessType?->name }}</td>
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
                                                <label for="INN">Инн</label>
                                                <input type="number" class="form-control" name="INN"
                                                       value="{{ $organization->INN }}">
                                            </div>

                                            <div class="form-group">
                                                <label for="business_type_id">Тип бизнеса <span
                                                        class="text-danger">*</span></label>
                                                <select
                                                    class="form-control form-control-sm @error('business_type_id') is-invalid @enderror"
                                                    name="business_type_id" required>
                                                    <option value="">Выберите тип бизнеса</option>
                                                    @foreach($businessTypes as $businessType)
                                                        <option
                                                            value="{{ $businessType->id }}" {{ $organization->business_type_id == $businessType->id ? 'selected' : '' }}>
                                                            {{ $businessType->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('business_type_id')
                                                <span class="text-danger">{{ $message }}</span>
                                                @enderror
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
                            <td>{{ $transaction->tariff?->price }}</td>
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

                        <div class="form-group">
                            <label for="phone">Телефон <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="phone" placeholder="Телефон">
                        </div>

                        <div class="form-group">
                            <label for="INN">Инн <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="INN" placeholder="ИНН">
                        </div>
                        <div class="form-group">
                            <label for="business_type_id">Тип бизнеса <span class="text-danger">*</span></label>
                            <select class="form-control form-control-sm @error('business_type_id') is-invalid @enderror"
                                    name="business_type_id" required>
                                <option value="">Выберите тип бизнеса</option>
                                @foreach($businessTypes as $businessType)
                                    <option
                                        value="{{ $businessType->id }}" {{ old('business_type_id') == $businessType->id ? 'selected' : '' }}>
                                        {{ $businessType->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('business_type_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="sub_domain">Адрес <span class="text-danger">*</span></label>
                            <textarea name="address" cols="30" rows="10" placeholder="Адрес"
                                      class="form-control"></textarea>
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
                            <label for="name">Дата</label>
                            <input type="date" class="form-control" name="date" id="date">
                        </div>

                        <div class="form-group">
                            <label for="phone">Сумма</label>
                            <input type="number" class="form-control" name="sum" placeholder="Сумма">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </div>
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
                                <strong>{{ $history->user->name }}</strong> <i
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

@endsection
