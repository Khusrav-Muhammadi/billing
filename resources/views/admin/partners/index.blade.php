@extends('layouts.app')

@section('title')
    Партнеры
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Партнеры</h4>
        <a href="{{ route('partner.create') }}" type="button" class="btn btn-primary">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>E-mail</th>
                    <th>Статус</th>
                    <th>Адрес</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($partners as $partner)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $partner->name }}</td>
                        <td>{{ $partner->phone }}</td>
                        <td>{{ $partner->email }}</td>
                        <td>{{ $partner->partnerStatus?->name }}</td>
                        <td>{{ $partner->address }}</td>
                        <td>
                            <a href="{{ route('partner.edit', $partner->id) }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="" data-bs-toggle="modal" data-bs-target="#deleteClient{{$partner->id}}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>
                    <div class="modal fade" id="deleteClient{{$partner->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('partner.delete', $partner->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление клиента</h5>
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

@endsection

