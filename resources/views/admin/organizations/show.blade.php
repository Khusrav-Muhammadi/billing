@extends('layouts.app')

@section('title')
    Организация - {{ $organization->name }}
@endsection

@section('content')

    <a href="#" onclick="history.back();" class="btn btn-outline-danger" style="margin-bottom: 10px">Назад</a>
    <a href="" data-bs-toggle="modal" data-bs-target="#history" type="button"
       class="btn btn-outline-dark mb-2 ml-5">
        <i class="mdi mdi-history" style="font-size: 30px"></i>
    </a>
    <div class="card">
        <!-- Первая строка -->
        <div class="row mb-3">
            <div class="col-4 ml-5 mt-3">
                <label for="name">Наименование</label>
                <input type="text" class="form-control" name="name" value="{{ $organization->name }}" disabled>
            </div>
            <div class="col-4 mt-3">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" name="phone" value="{{ $organization->phone }}" disabled>
            </div>
            <div class="col-3 mt-3">
                <label for="INN">ИНН</label>
                <input type="number" class="form-control" name="INN" value="{{ $organization->INN }}" disabled>
            </div>
        </div>

        <div class="col-6 mb-5 ml-4 mr-5">
            <label for="address">Адрес</label>
            <textarea name="address" cols="30" rows="5" placeholder="Адрес" class="form-control"
                      disabled>{{ $organization->address }}</textarea>
        </div>
    </div>

    <div class="card-body w-75">
        <div class="table-responsive">
            <h4 class="card-title">Подключенные пакеты</h4>
            <a href="" data-bs-toggle="modal" data-bs-target="#addPack" type="button"
               class="btn btn-outline-primary mb-2">Подключить</a>
            <div class="d-flex">
                <div class="card table-container flex-fill mr-3">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Пакет</th>
                            <th>Дата подключения</th>
                            <th>Действие</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($organization->packs as $pack)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $pack->pack?->name }}</td>
                                <td>{{ $pack->date }}</td>
                                <td>
                                    <a href="" data-bs-toggle="modal" data-bs-target="#deletePack{{$pack->id}}">
                                        <i style="color:red; font-size: 30px" class="mdi mdi-delete"></i>
                                    </a>
                                </td>
                            </tr>
                            <div class="modal fade" id="deletePack{{$pack->id}}" tabindex="-1"
                                 aria-labelledby="exampleModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form action="{{ route('organization.pack.destroy', $pack->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">Удаление пакета</h5>
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
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addPack" tabindex="-1" aria-labelledby="exampleModalLabel"
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
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="history" tabindex="-1"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"
                        id="exampleModalLabel"> История клиента</h5>
                </div>
                <div class="modal-body">
                    @foreach($organization->history as $history)
                        <div style="display: flex; justify-content: space-between;">
                            <h4>{{ $history->status }}</h4>
                            <span>
                                <strong>{{ $history->user->name }}</strong> <i
                                    style="font-size: 14px">{{ $history->created_at->format('d.m.Y H:i') }}</i>
                            </span>
                        </div>
                        <div class="ml-3" style="font-size: 14px">
                            @foreach ($history->changes as $change)
                                @php
                                    $bodyData = json_decode($change->body, true);
                                @endphp

                                @foreach ($bodyData as $key => $value)
                                    @if($key == 'name') Имя: <br>
                                    @elseif($key == 'phone') Телефон: <br>
                                    @elseif($key == 'email') Почта: <br>
                                    @elseif($key == 'client_type') Тип клиента: <br>
                                    @elseif($key == 'business_type') Тип бизнеса: <br>
                                    @elseif($key == 'tariff') Тариф: <br>
                                    @elseif($key == 'has_access') Доступ: <br>
                                    @endif
                                    @if($key == 'has_access')
                                            <p style="margin-left: 20px;">{{ $value['previous_value'] == 0 ? 'Отключен' : 'Подключён' }}
                                                ==> {{ $value['new_value'] == 0 ? 'Отключен' : 'Подключён' }}</p>
                                    @else
                                        <p style="margin-left: 20px;">{{ $value['previous_value'] ?? 'N/A' }}
                                            ==> {{ $value['new_value'] ?? 'N/A' }}</p>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                        <hr>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
