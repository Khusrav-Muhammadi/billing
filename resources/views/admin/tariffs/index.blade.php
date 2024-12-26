@extends('layouts.app')

@section('title')
    Тарифы
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Тарифы</h4>
        <a href="" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Цена</th>
                    <th>Кол-во пользователей</th>
                    <th>Кол-во лидов</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tariffs as $tariff)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $tariff->name }}</td>
                        <td>{{ $tariff->price }}</td>
                        <td>{{ $tariff->lead_count }}</td>
                        <td>{{ $tariff->user_count }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $tariff->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $tariff->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $tariff->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.update', $tariff->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение тарифа</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">ФИО</label>
                                            <input type="text" class="form-control" name="name" value="{{ $tariff->name }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Цена</label>
                                            <input type="number" class="form-control" name="price" value="{{ $tariff->price }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Кол-во пользователей</label>
                                            <input type="number" class="form-control" name="user_count" value="{{ $tariff->user_count }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Кол-во лидов</label>
                                            <input type="number" class="form-control" name="lead_count" value="{{ $tariff->lead_count }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Кол-во проектов</label>
                                            <input type="number" class="form-control" name="project_count" value="{{ $tariff->project_count }}">
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

                    <div class="modal fade" id="delete{{ $tariff->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.delete', $tariff->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление тарифа</h5>
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
                            <label for="name">Кол-во лидов</label>
                            <input type="number" class="form-control" name="lead_count" placeholder="Кол-во лидов">
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
