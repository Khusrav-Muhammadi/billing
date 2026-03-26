@extends('layouts.app')

@section('title')
    Организации
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Изменение организации</h4>

        <form method="POST" action="{{ route('organization.update', $organization->id) }}">
            @csrf
            @method('PATCH')
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" value="{{ $organization->name }}">
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" name="phone" value="{{ $organization->phone }}">
            </div>

            <div class="form-group">
                <label for="INN">Инн</label>
                <input type="number" class="form-control" name="INN" value="{{ $organization->INN }}">
            </div>

            <div class="form-group">
                <label for="license">Лицензия</label>
                <input type="text" class="form-control" name="license" value="{{ $organization->license }}">
            </div>

            <div class="form-group">
                <label for="client_id">Клиент</label>
                <select class="form-control form-control-sm" name="client_id" >
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="sale_id">Скидка</label>
                <select class="form-control form-control-sm" name="sale_id">
                    @foreach($sales as $sale)
                        <option value="{{ $sale->id }}">{{ $sale->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="address">Адрес</label>
                <textarea name="address" cols="30" rows="10" class="form-control">{{ $organization->address }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Изменить </button>

        </form>
    </div>

@endsection

