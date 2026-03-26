@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Изменение клиента</h4>
        <a href="#" onclick="history.back();" class="btn btn-outline-danger" style="margin-bottom: 10px" >Назад</a>
        <form method="POST" action="{{ route('client.update', $client->id) }}">
            @csrf
            @method('PATCH')
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" value="{{ $client->name }}">
                @if($errors->has('name')) <p
                    style="color: red;">{{ $errors->first('name') }}</p> @endif
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="number" class="form-control" name="phone" value="{{ $client->phone }}">
                @if($errors->has('phone')) <p
                    style="color: red;">{{ $errors->first('phone') }}</p> @endif
            </div>

            <div class="form-group">
                <label for="sub_domain">Поддомен</label>
                <input type="text" class="form-control" name="sub_domain" value="{{ $client->sub_domain }}" disabled>
                @if($errors->has('sub_domain')) <p
                    style="color: red;">{{ $errors->first('sub_domain') }}</p> @endif
            </div>

            <div class="form-group">
                <label for="tariff_id">Тариф</label>
                <select class="form-control form-control-sm" name="tariff_id">
                    @foreach($tariffs as $tariff)
                        <option value="{{ $tariff->id }}" {{ $client->tariff_id == $tariff->id ? 'selected': '' }}>{{ $tariff->name }}</option>
                    @endforeach
                </select>
                @if($errors->has('tariff_id')) <p
                    style="color: red;">{{ $errors->first('tariff_id') }}</p> @endif
            </div>

            <div class="form-group">
                <label for="partner_id">Страна</label>
                <select class="form-control form-control-sm @error('country_id') is-invalid @enderror"
                        name="partner_id">
                    <option value="">Выберите страну</option>
                    @foreach($countries as $country)
                        <option
                            value="{{ $country->id }}" {{  ($client->country_id == $country->id) ? 'selected' : '' }}>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </select>
                @error('country_id')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="sale_id">Скидка</label>
                <select class="form-control form-control-sm" name="sale_id">
                    @foreach($sales as $sale)
                        <option value="{{ $sale->id }}" {{ $client->sale_id == $sale->id ? 'selected': '' }}>{{ $sale->name }}</option>
                    @endforeach
                </select>
                @if($errors->has('sale_id')) <p
                    style="color: red;">{{ $errors->first('sale_id') }}</p> @endif
            </div>
            @if($client->is_demo)
                <div class="form-group col-2" style="display: flex; align-items: center;">
                    <label for="is_demo" style="margin-right: 10px;">Демо версия:</label>
                    <input type="checkbox" name="is_demo" class="form-control" style="width: 30px; margin: 0;" {{ $client->is_demo ? 'checked' : '' }}>
                </div>
            @endif
            <button type="submit" class="btn btn-primary mr-2"> Изменить </button>

        </form>
    </div>

@endsection

