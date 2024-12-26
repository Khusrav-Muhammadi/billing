@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Создание клиента</h4>

        <form method="POST" action="{{ route('client.store') }}">
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
                <label for="sub_domain">Поддомен</label>
                <input type="text" class="form-control" name="sub_domain" placeholder="Поддомен сайта">
            </div>

            <div class="form-group">
                <label for="business_type_id">Тип бизнеса</label>
                <select class="form-control form-control-sm" name="business_type_id">
                    @foreach($businessTypes as $businessType)
                        <option value="{{ $businessType->id }}">{{ $businessType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="tariff_id">Тариф</label>
                <select class="form-control form-control" name="tariff_id">
                    @foreach($tariffs as $tariff)
                        <option value="{{ $tariff->id }}">{{ $tariff->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="sale_id">Скидка</label>
                <select class="form-control form-control" name="sale_id">
                    <option value="">Выберите скидку</option>
                    @foreach($sales as $sale)
                        <option value="{{ $sale->id }}">{{ $sale->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Сохранить </button>

        </form>
    </div>

@endsection

