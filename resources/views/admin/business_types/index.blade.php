@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Тип бизнеса</h4>
        <a href="" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($businessTypes as $businessType)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $businessType->name }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $businessType->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $businessType->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $businessType->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('business_type.update', $businessType->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение типа бизнеса</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">ФИО</label>
                                            <input type="text" class="form-control" name="name" value="{{ $businessType->name }}">
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

                    <div class="modal fade" id="delete{{ $businessType->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('business_type.delete', $businessType->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление типа бизнеса</h5>
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
            <form action="{{ route('business_type.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создание тип бизнеса</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">ФИО</label>
                            <input type="text" class="form-control" name="name" placeholder="ФИО">
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
