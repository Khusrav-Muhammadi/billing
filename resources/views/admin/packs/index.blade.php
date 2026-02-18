@extends('layouts.app')

@section('title')
    Пакеты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Пакеты</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Количество</th>
                    <th>Цена</th>
                    <th>Тариф</th>
                    <th>Тип оплаты</th>
                    <th>Внешный/Внутренный</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($packs as $pack)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $pack->name }}</td>
                        <td>{{ $pack->amount }}</td>
                        <td>{{ $pack->price }}</td>
                        <td>{{ $pack->tariff?->name }}</td>
                        <td>{{ $pack->payment_type }}</td>
                        <td>{{ $pack->is_external ? 'Внешный' : 'Внутренный' }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $pack->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $pack->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $pack->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('pack.update', $pack->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение пакета</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">Название</label>
                                            <input type="text" class="form-control" name="name" value="{{ $pack->name }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Количество</label>
                                            <input type="text" class="form-control" name="amount" value="{{ $pack->amount }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Цена</label>
                                            <input type="number" class="form-control" name="price" value="{{ $pack->price }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Тип оплаты</label>
                                            <select name="payment_type" id="" class="form-control">
                                                <option value="Ежемесячный" {{ $pack->payment_type == 'Ежемесячный' ? "selected" : '' }}>Ежемесячный</option>
                                                <option value="Разовый" {{ $pack->pament_type == 'Разовый' ? "selected" : '' }}>Разовый</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Внешный</label>
                                            <input type="checkbox" class="form-check-inline custom-checkbox" name="is_external" {{ $pack->is_external ? 'checked' : '' }} style="width: 20px; height: 20px">
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

                    <div class="modal fade" id="delete{{ $pack->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('pack.delete', $pack->id) }}" method="POST">
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
