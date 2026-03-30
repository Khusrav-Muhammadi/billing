@extends('layouts.app')

@section('title')
    Счета
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Счета</h4>
        <a href="" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Валюта</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($accounts as $account)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->currency?->name }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $account->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $account->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $account->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('account.update', $account->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение счета</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">Наименование</label>
                                            <input type="text" class="form-control" name="name" value="{{ $account->name }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="currency_id">Валюта</label>
                                            <select name="currency_id" class="form-control" required>
                                                <option value="">Выберите валюту</option>
                                                @foreach($currencies as $currency)
                                                    <option value="{{ $currency->id }}" {{ (int) $account->currency_id === (int) $currency->id ? 'selected' : '' }}>
                                                        {{ $currency->name }}
                                                    </option>
                                                @endforeach
                                            </select>
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

                    <div class="modal fade" id="delete{{ $account->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('account.delete', $account->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление счета</h5>
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
            <form action="{{ route('account.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создание счета</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Наименование</label>
                            <input type="text" class="form-control" name="name" placeholder="Наименование" required>
                        </div>
                        <div class="form-group">
                            <label for="currency_id">Валюта</label>
                            <select name="currency_id" class="form-control" required>
                                <option value="">Выберите валюту</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}">{{ $currency->name }}</option>
                                @endforeach
                            </select>
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
