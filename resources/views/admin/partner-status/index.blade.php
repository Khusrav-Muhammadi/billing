@extends('layouts.app')

@section('title')
    Тарифы
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Статус партнера</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Название</th>
                    <th>Количество подключений</th>
                    <th>Процент подключения партнера</th>
                    <th>Процент тариф партнера</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tariffs as $tariff)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $tariff->name }}</td>
                        <td>{{ $tariff->connect_amount }}</td>
                        <td>{{ $tariff->organization_connect_percent }} $</td>
                        <td>{{ $tariff->tariff_price_percent }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $tariff->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $tariff->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('partner-status.store', $tariff->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение статуса</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">Название</label>
                                            <input type="text" class="form-control" name="name" value="{{ $tariff->name }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Количество подключений</label>
                                            <input type="number" class="form-control" name="connect_amount" value="{{ $tariff->price }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Процент подключений организации</label>
                                            <input type="number" class="form-control" name="organization_connect_percent" value="{{ $tariff->user_count }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Процент от тарифов</label>
                                            <input type="number" class="form-control" name="tariff_price_percent" value="{{ $tariff->project_count }}">
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

    <div class="modal fade" id="create" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('tariff.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создание тарифа</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Название</label>
                            <input type="text" class="form-control" name="name" placeholder="Название">
                        </div>
                        <div class="form-group">
                            <label for="name">Цена</label>
                            <input type="number" class="form-control" name="price" placeholder="Цена">
                        </div>
                        <div class="form-group">
                            <label for="name">Кол-во пользователей</label>
                            <input type="number" class="form-control" name="user_count" placeholder="Кол-во пользователей">
                        </div>
                        <div class="form-group">
                            <label for="name">Кол-во проектов</label>
                            <input type="number" class="form-control" name="project_count" placeholder="Кол-во проектов">
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
