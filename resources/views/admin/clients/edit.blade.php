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
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" name="phone" value="{{ $client->phone }}">
            </div>

            <div class="form-group">
                <label for="sub_domain">Поддомен</label>
                <input type="text" class="form-control" name="sub_domain" value="{{ $client->sub_domain }}">
            </div>

            <div class="form-group">
                <label for="business_type_id">Тип бизнеса</label>
                <select class="form-control form-control-sm" name="business_type_id">
                    @foreach($businessTypes as $businessType)
                        <option value="{{ $businessType->id }}" {{ $client->business_type_id == $businessType->id ? 'selected': '' }}>{{ $businessType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="business_type_id">Тариф</label>
                <select class="form-control form-control-sm" name="tariff_id">
                    @foreach($tariffs as $tariff)
                        <option value="{{ $tariff->id }}" {{ $client->tariff_id == $tariff->id ? 'selected': '' }}>{{ $tariff->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="business_type_id">Скидка</label>
                <select class="form-control form-control-sm" name="sale_id">
                    @foreach($sales as $sale)
                        <option value="{{ $sale->id }}" {{ $client->sale_id == $sale->id ? 'selected': '' }}>{{ $sale->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Изменить </button>

        </form>
    </div>

@endsection

