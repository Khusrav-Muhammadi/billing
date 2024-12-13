@extends('layouts.app')

@section('title')
    Клиент - {{ $client->name }}
@endsection

@section('content')

    <div class="card-body">
        <div class="table-responsive">
            <h4 class="card-title">Организации</h4>
            <a href="" data-bs-toggle="modal" data-bs-target="#createOrganization" type="button" class="btn btn-primary">Создать</a>
            <div class="d-flex">
                <div class="card table-container flex-fill mr-3">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Имя</th>
                            <th>Телефон</th>
                            <th>Тариф</th>
                            <th>Скидка</th>
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
                                <td>{{ $organization->tariff?->name }}</td>
                                <td>{{ $organization->sale?->name }}</td>
                                <td>
                                    <input type="checkbox" class="form-control" name="has_access" style="width: 30px"
                                           data-organization-id="{{ $organization->id }}" data-client-id="{{ $client->id }}" {{ $organization->has_access ? 'checked' : '' }}
                                    >
                                </td>
                                <td>
                                    <a href="" data-bs-toggle="modal" data-bs-target="#editOrganization{{$organization->id}}">
                                        <i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i>
                                    </a>
                                    <a href="" data-bs-toggle="modal" data-bs-target="#deleteOrganization{{$organization->id}}">
                                        <i style="color:red; font-size: 30px" class="mdi mdi-delete"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card table-container flex-fill ml-3">
                    <h4 class="card-title">Транзакции</h4>
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
                                <td>{{ $transaction->tariff->price }}</td>
                                <td>{{ $transaction->sale->amount }}</td>
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
                            <input type="text" class="form-control" name="phone" placeholder="Телефон">
                        </div>

                        <div class="form-group">
                            <label for="tariff_id">Тариф</label>
                            <select class="form-control form-control" name="tariff_id">
                                @foreach($tariffs as $tariff)
                                    <option value="{{ $tariff->id }}">{{ $tariff->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="sale_id">Скидка</label>
                            <select class="form-control form-control" name="sale_id">
                                @foreach($sales as $sale)
                                    <option value="{{ $sale->id }}">{{ $sale->name }}</option>
                                @endforeach
                            </select>
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
                            console.log('Ответ сервера:', text); // Посмотреть, что вернул сервер
                            return text ? JSON.parse(text) : {}; // Если текст пустой, вернуть пустой объект
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
