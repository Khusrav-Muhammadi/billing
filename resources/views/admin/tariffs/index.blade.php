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
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tariffs as $tarrif)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $tarrif->name }}</td>
                        <td>{{ $tarrif->price }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $tarrif->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $tarrif->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $tarrif->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.update', $tarrif->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение тарифа</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">ФИО</label>
                                            <input type="text" class="form-control" name="name" value="{{ $tarrif->name }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Цена</label>
                                            <input type="number" class="form-control" name="price" value="{{ $tarrif->price }}">
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

                    <div class="modal fade" id="delete{{ $tarrif->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.delete', $tarrif->id) }}" method="POST">
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
                            <label for="name">Имя</label>
                            <input type="text" class="form-control" name="name" placeholder="ФИО">
                        </div>
                        <div class="form-group">
                            <label for="name">Цена</label>
                            <input type="number" class="form-control" name="price" placeholder="Цена">
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
