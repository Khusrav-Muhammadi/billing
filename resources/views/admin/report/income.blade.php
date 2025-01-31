@extends('layouts.app')

@section('title')
    Отчёт о доходности
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Отчёт о доходности</h4>
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
                        <td>{{ mb_ucfirst($income['month']) }}</td>
                        <td>{{ $income['total'] }}</td>
                        <td>{{ $income['Стандарт'] ?? 0 }}</td>
                        <td>{{ $income['Премиум'] ?? 0 }}</td>
                        <td>{{ $income['VIP'] ?? 0 }}</td>

                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
