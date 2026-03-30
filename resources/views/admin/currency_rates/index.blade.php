@extends('layouts.app')

@section('title')
    Курс валюты
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Курс валюты</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Валюта</th>
                    <th>Код</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($currencies as $currency)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $currency->name }}</td>
                        <td>{{ $currency->symbol_code }}</td>
                        <td>
                            <a href="{{ route('currency-rate.show', $currency->id) }}" title="Смотреть курсы">
                                <i class="mdi mdi-eye" style="font-size: 30px"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
