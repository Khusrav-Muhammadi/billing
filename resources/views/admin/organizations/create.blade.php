@extends('layouts.app')

@section('title')
    Организация
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Создание организации</h4>

        <form method="POST" action="{{ route('organization.store') }}">
            @csrf
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" placeholder="ФИО">
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" name="phone" placeholder="Телефон">
            </div>

            <div class="form-group">
                <label for="INN">Инн</label>
                <input type="number" class="form-control" name="INN" placeholder="ИНН">
            </div>

            <div class="form-group">
                <label for="license">Лицензия</label>
                <input type="text" class="form-control" name="license" placeholder="Лицензия">
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
                <label for="sub_domain">Адрес</label>
                <textarea name="address" cols="30" rows="10" placeholder="Адрес" class="form-control" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Сохранить </button>

        </form>
    </div>

@endsection

