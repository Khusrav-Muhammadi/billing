@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Клиенты</h4>
        <a href="{{ route('client.create') }}" type="button" class="btn btn-primary">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Поддомен</th>
                    <th>Тип бизнеса</th>
                    <th>Баланс</th>
                    <th>Статус</th>
                    <th>Тип подключения</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($clients as $client)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $client->name }}</td>
                        <td>{{ $client->phone }}</td>
                        <td>{{ $client->sub_domain }}</td>
                        <td>{{ $client->businessType?->name }}</td>
                        <td>{{ $client->balance }}</td>
                        <td>
                            @if($client->is_active) <p style="color: #00bb00">Активный</p> @else <p style="color: red">Неактивный</p> @endif
                        </td>
                        <td>
                            @if($client->is_demo) Демо версия @else Боевая версия @endif
                        </td>
                        <td>
                            <a href="{{ route('client.edit', $client->id) }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="{{ route('client.show', $client->id) }}"><i class="mdi mdi-eye" style="font-size: 30px"></i></a>
                            <a href="" data-bs-toggle="modal" data-bs-target="#deleteClient{{$client->id}}"><i style="{{ $client->is_active ? 'color:green;': 'color:red;' }} font-size: 30px" class="mdi mdi-power"></i></a>
                        </td>
                    </tr>
                    <div class="modal fade" id="deleteClient{{$client->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('client.activation', $client->id) }}" method="POST">
                                @csrf

                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel"> {{ $client->is_active ? 'Деактивация' : 'Активация' }} клиента</h5>
                                    </div>
                                    <div class="modal-body">
                                        Вы уверены что хотите {{ $client->is_active ? 'деактивировать' : 'активировать' }} этого клиента?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-{{ $client->is_active ? 'danger' : 'success' }}">{{ $client->is_active ? 'Деактивировать' : 'Активировать' }}</button>
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

