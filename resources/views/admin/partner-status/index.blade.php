@extends('layouts.app')

@section('title')
    Тарифы
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Статус партнера</h4>
        <a href="" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Название</th>
                    <th>Сумма подключений</th>
                    <th>Процент подключения партнера</th>
                    <th>Процент тариф партнера</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($partnerStatuses as $partnerStatus)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $partnerStatus->name }}</td>
                        <td>{{ $partnerStatus->connect_amount }}</td>
                        <td>{{ $partnerStatus->organization_connect_percent }}%</td>
                        <td>{{ $partnerStatus->tariff_price_percent }}%</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $partnerStatus->id }}">
                                <i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i>
                            </a>
                        </td>
                    </tr>

                    <!-- Модальное окно для редактирования статуса партнера -->
                    <div class="modal fade" id="edit{{ $partnerStatus->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('partner-status.update', $partnerStatus->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение статуса</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">Название</label>
                                            <input type="text" class="form-control" name="name" value="{{ $partnerStatus->name }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="connect_amount">Количество подключений</label>
                                            <input type="number" class="form-control" name="connect_amount" value="{{ $partnerStatus->connect_amount }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="organization_connect_percent">Процент подключения организации</label>
                                            <input type="number" class="form-control" name="organization_connect_percent" value="{{ $partnerStatus->organization_connect_percent }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="tariff_price_percent">Процент от тарифов</label>
                                            <input type="number" class="form-control" name="tariff_price_percent" value="{{ $partnerStatus->tariff_price_percent }}">
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
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Модальное окно для создания статуса партнера -->
    <div class="modal fade" id="create" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('partner-status.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создание статуса партнера</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Название</label>
                            <input type="text" class="form-control" name="name" placeholder="Название">
                        </div>
                        <div class="form-group">
                            <label for="connect_amount">Сумма подключений</label>
                            <input type="number" class="form-control" name="connect_amount" placeholder="Сумма подключений">
                        </div>
                        <div class="form-group">
                            <label for="organization_connect_percent">Процент подключения организации</label>
                            <input type="number" class="form-control" name="organization_connect_percent" placeholder="Процент подключения организации">
                        </div>
                        <div class="form-group">
                            <label for="tariff_price_percent">Процент от тарифов</label>
                            <input type="number" class="form-control" name="tariff_price_percent" placeholder="Процент от тарифов">
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
