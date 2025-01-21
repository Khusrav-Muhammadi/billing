@extends('layouts.app')

@section('title')
    Клиент - {{ $client->name }}
@endsection

@section('content')

    <div class="card-body">

        <div class="table-responsive">
            <h4 class="card-title">Организации</h4>
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
                <a href="#" onclick="history.back();" class="btn btn-outline-danger">Назад</a>
                @if($client->balance >= $client->tariff?->price && $client->is_active)
                    <a href="" data-bs-toggle="modal" data-bs-target="#createOrganization" type="button"
                       class="btn btn-primary">Создать</a>
                @endif
                <a href="" data-bs-toggle="modal" data-bs-target="#activation" type="button"
                   class="btn btn-outline-{{ $client->is_active ? 'success' : 'danger' }} ml-5" ><i class="mdi mdi-power" style="font-size: 30px"></i></a>
            </div>
            <div class="d-flex">
                <div class="card table-container flex-fill mr-3">
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
                                           data-organization-id="{{ $organization->id }}"
                                           data-client-id="{{ $client->id }}" {{ $organization->has_access ? 'checked' : '' }}
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

                            <div class="modal fade" id="editOrganization{{$organization->id}}" tabindex="-1"
                                 aria-labelledby="exampleModalLabel"
                                 aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="POST" action="{{ route('organization.update', $organization->id) }}">
                                        @csrf
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

                            <div class="modal fade" id="deleteOrganization{{$organization->id}}" tabindex="-1"
                                 aria-labelledby="exampleModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form action="{{ route('organization.destroy', $organization->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">Удаление организации</h5>
                                            </div>
                                            <div class="modal-body">
                                                Вы уверены что хотите удалить эти данные?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    Отмена
                                                </button>
                                                <button type="submit" class="btn btn-danger">Удалить</button>
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
                        <h4 class="card-title m-2">Транзакции</h4>
                        <a href="" data-bs-toggle="modal" data-bs-target="#createTransaction" type="button"
                           class="btn btn-primary m-2">Создать</a>
                    </div>
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Дата</th>
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
                                <td>{{ $transaction->tariff?->price }}</td>
                                <td>{{ $transaction->sale?->amount }}</td>
                                <td>{{ $transaction->sum }}</td>
                                <td>{{ $transaction->type }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
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
                            <label for="name">Наименование</label>
                            <input type="text" class="form-control" name="name" placeholder="Введите название">
                        </div>

                        <div class="form-group">
                            <label for="phone">Телефон</label>
                            <input type="number" class="form-control" name="phone" placeholder="Телефон">
                        </div>

                        <div class="form-group">
                            <label for="INN">Инн</label>
                            <input type="number" class="form-control" name="INN" placeholder="ИНН">
                        </div>

                        <div class="form-group">
                            <label for="sub_domain">Адрес</label>
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
            <form action="{{ route('client.activation', $client->id) }}" method="POST">
                @csrf

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel"> {{ $client->is_active ? 'Деактивация' : 'Активация' }} клиента</h5>
                    </div>
                    <div class="modal-body">
                        Вы уверены что хотите {{ $client->is_active ? 'активировать' : 'деактивировать' }} эти данные?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Отмена
                        </button>
                        <button type="submit" class="btn btn-{{ $client->is_active ? 'danger' : 'success' }}">{{ $client->is_active ? 'Деактивировать' : 'Активировать' }}</button>
                    </div>
                </div>
            </form>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            if (!csrfToken) {
                console.error('CSRF token not found');
                return;
            }

            const checkboxes = document.querySelectorAll('input[name="has_access"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const organizationId = this.dataset.organizationId;
                    const clientId = this.dataset.clientId;
                    const hasAccess = this.checked; // Текущее состояние чекбокса

                    fetch('/organization/access', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            organization_id: organizationId,
                            client_id: clientId,
                            has_access: hasAccess
                        })
                    })
                        .then(response => response.text())
                        .then(text => {
                            console.log('Ответ сервера:', text);
                            return text ? JSON.parse(text) : {};
                        })
                        .then(data => {
                            console.log('Распознанный JSON:', data);
                        })
                        .catch(error => {
                            console.error('Ошибка:', error);
                        });

                });
            });
        });

    </script>

@endsection
