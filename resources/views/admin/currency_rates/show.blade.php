@extends('layouts.app')

@section('title')
    Курсы {{ $currency->symbol_code }}
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Курсы: 1 USD = ? {{ $currency->symbol_code }}</h4>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <a href="{{ route('currency-rate.index') }}" type="button" class="btn btn-light">Назад</a>
        <a href="" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create">Добавить курс</a>

        <div class="table-responsive mt-3">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Курс</th>
                    <th>Дата</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($rates as $rate)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $rate->rate }} {{ $currency->symbol_code }}</td>
                        <td>{{ optional($rate->rate_date)->format('Y-m-d') ?? optional($rate->created_at)->format('Y-m-d') }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $rate->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $rate->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $rate->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('currency-rate.update', $rate->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение курса</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Курс</label>
                                            <input type="number" step="0.000001" min="0.000001" class="form-control" name="rate" value="{{ $rate->rate }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Дата</label>
                                            <input type="date" class="form-control" name="rate_date" value="{{ old('rate_date', optional($rate->rate_date)->format('Y-m-d') ?? optional($rate->created_at)->format('Y-m-d')) }}" required>
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

                    <div class="modal fade" id="delete{{ $rate->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('currency-rate.delete', $rate->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление курса</h5>
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
            <form action="{{ route('currency-rate.store', $currency->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Добавить курс</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Курс</label>
                            <input type="number" step="0.000001" min="0.000001" class="form-control" name="rate" placeholder="Например: 10.910000" required>
                        </div>
                        <div class="form-group">
                            <label>Дата</label>
                            <input type="date" class="form-control" name="rate_date" value="{{ old('rate_date', now()->format('Y-m-d')) }}" required>
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
