@extends('layouts.app')

@section('title')
    Организация
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Организация</h4>
        <a href="{{ route('organization.create') }}" type="button" class="btn btn-primary">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>ИНН</th>
                    <th>Клиент</th>
                    <th>Лицензия</th>
                    <th>Скидка</th>
                    <th>Адрес</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($organizations as $organization)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $organization->name }}</td>
                        <td>{{ $organization->phone }}</td>
                        <td>{{ $organization->INN }}</td>
                        <td>{{ $organization->client->name }}</td>
                        <td>{{ $organization->license }}</td>
                        <td>{{ $organization->sale?->name }}</td>
                        <td>{{ $organization->address }}</td>
                        <td>
                            <a href="{{ route('organization.edit', $organization->id) }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="" data-bs-toggle="modal" data-bs-target="#deleteClient{{$organization->id}}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>
                    <div class="modal fade" id="deleteClient{{$organization->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('organization.destroy', $organization->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удалить организацию</h5>
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

