@extends('layouts.app')

@section('title')
    Отчёт о доходности
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Отчёт о доходности</h4>
        <a href="" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Месяц</th>
                    <th>Общ. сумма</th>
                    <th>Стандарт</th>
                    <th>Премиум</th>
                    <th>VIP</th>
                </tr>
                </thead>
                <tbody>
                @foreach($incomes as $income)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $income['month'] }}</td>
                        <td>{{ $income['total'] }}</td>
                        <td>@if(isset($income['Стандарт'])){{ $income['Стандарт'] }}@else 0 @endif</td>
                        <td>@if(isset($income['Премиум'])){{ $income['Премиум'] }}@else 0 @endif</td>
                        <td>@if(isset($income['VIP'])){{ $income['VIP'] }}@else 0 @endif</td>

                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
