@extends('layouts.app')

@section('title')
    Прайслист
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Прайслист</h4>
        <div class="table-responsive">
            <a href="#" data-bs-toggle="modal" data-bs-target="#create" type="button" class="btn btn-primary">Добавить</a>
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Дата</th>
                    <th>Валюта</th>
                    <th>Услуга</th>
                    <th>Клиент</th>
                    <th>Сумма</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($priceLists as $price)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $price->date }}</td>
                        <td>{{ $price->currency?->name }} $</td>
                        <td>{{ $price->tariff?->name }}</td>
                        <td>{{ $price->client?->name }}</td>
                        <td>{{ $price->sum }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $price->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $price->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $price->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('price_list.update', $price ->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение прайслиста</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">Наименование</label>
                                            <input type="text" class="form-control" name="name" value="{{ $price->name }}">
                                        </div>
{{--                                        <div class="form-group">--}}
{{--                                            <label for="name">Цена</label>--}}
{{--                                            <input type="number" class="form-control" name="price" value="{{ $tariff->price }}">--}}
{{--                                        </div>--}}
                                        <div class="form-group">
                                            <label for="name">Скидка</label>
                                            <input type="number" class="form-control" name="price" value="{{ $price->price }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Кол-во пользователей</label>
                                            <input type="number" class="form-control" name="user_count" value="{{ $price->user_count }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Кол-во проектов</label>
                                            <input type="number" class="form-control" name="project_count" value="{{ $price->project_count }}">
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Дата завршения</label>
                                            <input type="date" class="form-control" name="date">
                                        </div>

                                        <div class="form-group">
                                            <label for="name">Тариф</label>
                                            <input type="checkbox" class="form-check-inline custom-checkbox" name="tariff" {{ $price->tariff ? 'checked' : '' }} style="width: 20px; height: 20px">
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

                    <div class="modal fade" id="delete{{ $price->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.delete', $price->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление прайслиста</h5>
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
            <form action="{{ route('price_list.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создание прайслиста</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="tariff_id">Тариф <span class="text-danger">*</span></label>
                            <select class="form-control form-control @error('tariff_id') is-invalid @enderror"
                                    name="tariff_id" required>
                                <option value="">Выберите тариф</option>
                                @foreach($tariffs as $tariff)
                                    <option
                                        value="{{ $tariff->id }}" {{ (isset($partnerRequest) && $partnerRequest->tariff_id == $tariff->id) || old('tariff_id') == $tariff->id ? 'selected' : '' }}>
                                        {{ $tariff->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('tariff_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="tariff_id">Клиент</label>
                            <select class="form-control form-control @error('client_id') is-invalid @enderror"
                                    name="client_id">
                                <option value="">Выберите клиента</option>
                                @foreach($clients as $client)
                                    <option
                                        value="{{ $client->id }}" {{ (isset($partnerRequest) && $partnerRequest->$client == $client->id) || old('client_id') == $client->id ? 'selected' : '' }}>
                                        {{ $client->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('client_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="name">Дата</label>
                            <input type="date" class="form-control" name="date">
                        </div>
                        <div class="form-group">
                            <label for="name">Сумма</label>
                            <input type="number" class="form-control" name="sum">
                        </div>
                        <div class="form-group">
                            <label for="tariff_id">Валюта <span class="text-danger">*</span></label>
                            <select class="form-control form-control @error('currency_id') is-invalid @enderror"
                                    name="currency_id" required>
                                <option value="">Выберите клиента</option>
                                @foreach($currencies as $currency)
                                    <option
                                        value="{{ $currency->id }}" {{ (isset($partnerRequest) && $partnerRequest->currency_id == $currency->id) || old('currency_id') == $currency->id ? 'selected' : '' }}>
                                        {{ $currency->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
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
