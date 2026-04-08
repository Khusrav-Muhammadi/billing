@extends('layouts.app')

@section('title')
    Закрытие дня {{ $dayClosing->doc_number }}
@endsection

@section('content')
    <div class="card-body">
        <div class="mb-3">
            <a href="{{ route('day-closing.index') }}" class="btn btn-outline-danger">Назад</a>
        </div>

        <div class="card p-3 mb-4">
            <div class="row">
                <div class="col-md-3"><strong>Документ:</strong> {{ $dayClosing->doc_number }}</div>
                <div class="col-md-3"><strong>Дата:</strong> {{ optional($dayClosing->date)->format('d.m.Y') }}</div>
                <div class="col-md-3"><strong>Автор:</strong> {{ $dayClosing->author?->name ?? '-' }}</div>
                <div class="col-md-3"><strong>Организаций:</strong> {{ (int) $dayClosing->client_amount }}</div>
            </div>
        </div>

        <h5 class="mb-2">Клиенты</h5>
        <div class="table-responsive mb-4">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Клиент</th>
                    <th>Валюта</th>
                    <th>Баланс до начисления</th>
                    <th>Баланс после начисления</th>
                    <th>Снятая сумма за день</th>
                    <th>Статус после начисления</th>
                </tr>
                </thead>
                <tbody>
                @forelse($dayClosing->details as $detail)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $detail->organization?->name ?? '-' }}</td>
                        <td>{{ $detail->currency?->name ?? $detail->currency?->symbol_code ?? '-' }}</td>
                        <td>{{ number_format((float) $detail->balance_before_accrual, 4, '.', ' ') }}</td>
                        <td>{{ number_format((float) $detail->balance_after_accrual, 4, '.', ' ') }}</td>
                        <td>{{ number_format(max(0, (float) $detail->balance_before_accrual - (float) $detail->balance_after_accrual), 4, '.', ' ') }}</td>
                        <td>
                            <span class="badge {{ $detail->status_after_accrual ? 'badge-success' : 'badge-danger' }}">
                                {{ $detail->status_after_accrual ? 'Активный' : 'Пассивный' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">По документу нет данных</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-2">Детализация услуг</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Клиент</th>
                    <th>Услуга</th>
                    <th>Сумма услуги за месяц</th>
                    <th>Сумма за день</th>
                </tr>
                </thead>
                <tbody>
                @forelse($serviceRows as $serviceRow)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $serviceRow['organization_name'] }}</td>
                        <td>{{ $serviceRow['service_name'] }}</td>
                        <td>{{ number_format((float) $serviceRow['monthly_sum'], 4, '.', ' ') }}</td>
                        <td>{{ number_format((float) $serviceRow['daily_sum'], 4, '.', ' ') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Детализация услуг отсутствует</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
