@extends('layouts.app')

@section('title')
    Скидки
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Скидки</h4>
        <a href="" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Размер</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($sales as $sale)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $sale->name }}</td>
                        <td>{{ $sale->amount }} {{ $sale->sale_type == 'procent' ? '%' : 'с' }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $sale->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $sale->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $sale->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('sale.update', $sale->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение скидки</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">Название</label>
                                            <input type="text" class="form-control" name="name" value="{{ $sale->name }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Тип скидки</label>
                                            <select name="sale_type" id="" class="form-control">
                                                <option value="procent" {{ $sale->sale_type == 'procent' ? "selected" : '' }}>Процент</option>
                                                <option value="sum" {{ $sale->sale_type == 'sum' ? "selected" : '' }}>Сумма</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Размер</label>
                                            <input type="number" class="form-control" name="amount" value="{{ $sale->amount }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Активный</label>
                                            <input type="checkbox" class="form-check-inline custom-checkbox" name="active" {{ $sale->active ? 'checked' : '' }} style="width: 20px; height: 20px">
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

                    <div class="modal fade" id="delete{{ $sale->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('sale.delete', $sale->id) }}" method="POST">
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
            <form action="{{ route('sale.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создание скидки</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Название</label>
                            <input type="text" class="form-control" name="name" placeholder="Название скидки">
                        </div>
                        <div class="form-group">
                            <label for="name">Тип скидки</label>
                            <select name="sale_type" id="" class="form-control">
                                <option value="procent">Процент</option>
                                <option value="sum">Сумма</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="name">Размер скидки</label>
                            <input type="number" class="form-control" name="amount" placeholder="Размер скидки">
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
