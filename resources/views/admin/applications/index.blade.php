@extends('layouts.app')

@section('title')
    Коммерческие предложения
@endsection


@section('content')
    <div class="card-body">
        <h4 class="card-title">Оплата</h4>
        <p>Этот раздел предназначен для проведения оплаты аккаунтов ваших клиентов в shamCRM за вычетом вашей комиссии. От клиента вы получаете полную сумму за лицензии в соответствии с ценами на нашем сайте. В данном разделе вы выставляете счет для себя (он будет уже с учетом партнерской скидки) и оплачиваете его.
        </p>
        <a href="{{ route('application.create') }}" type="button" class="btn btn-primary mb-3">Оплатить</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Клиент</th>
                    <th>Дата операции</th>
                    <th>Email</th>
                    <th>Тариф</th>
                    <th>Период</th>
                    <th>Сумма оплаты </th>
                    <th>Статус операции</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                @forelse($offers as $offer)
                    <tr>
                        <td>{{ $offer->id }}</td>
                        <td>{{ $offer->client_name }}</td>
                        <td>{{ optional($offer->saved_at)->format('d.m.Y') }}</td>

                        <td>{{ $offer->client_email }}</td>
                        <td>{{ optional($offer->tariff)->tariff_name ?? '-' }}</td>
                        <td>{{ $offer->period_months }} мес.</td>
                        <td>{{ number_format($offer->grand_total, 2, '.', ' ') }} {{ $offer->currency }}</td>
                        <td>Не успешно</td>
                        <td>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center">Пока нет сохраненных Оплат</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            {{ $offers->links() }}
        </div>
    </div>
@endsection
