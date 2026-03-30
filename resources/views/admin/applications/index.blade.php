@extends('layouts.app')

@section('title')
    Коммерческие предложения
@endsection


@section('content')
    <div class="card-body">
        <h4 class="card-title">Оплата</h4>
        <p>Этот раздел предназначен для проведения оплаты аккаунтов ваших клиентов в shamCRM за вычетом вашей комиссии. От клиента вы получаете полную сумму за лицензии в соответствии с ценами на нашем сайте. В данном разделе вы выставляете счет для себя (он будет уже с учетом партнерской скидки) и оплачиваете его.
        </p>
        <a href="{{ route('application.create') }}" type="button" class="btn btn-primary mb-3">Добавить запрос</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Клиент</th>
                    <th>Партнер</th>
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
                    <tr class="offer-row" data-href="{{ route('application.show', $offer) }}" style="cursor: pointer;">
                        <td>{{ $offer->id }}</td>
                        <td>{{ $offer->client_name }}</td>
                        <td>{{ $offer->partner_name ?? '-' }}</td>
                        <td>{{ optional($offer->saved_at)->format('d.m.Y') }}</td>

                        <td>{{ $offer->payer_type === 'partner' ? ($offer->partner_email ?? '-') : ($offer->client_email ?? '-') }}</td>
                        <td>{{ optional($offer->tariff)->name ?? '-' }}</td>
                        <td>{{ $offer->period_months }} мес.</td>
                        <td>{{ number_format((float) $offer->payable_total, 2, '.', ' ') }} {{ $offer->payable_currency ?: $offer->currency }}</td>
                        <td>
                            @if($offer->locked_at)
                                <span class="badge badge-success">Ссылка сгенерирована</span>
                            @else
                                <span class="badge badge-warning">Черновик</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('application.show', $offer) }}" class="btn btn-sm btn-outline-primary">Просмотр</a>
                            @if(!$offer->locked_at)
                                <a href="{{ route('application.edit', $offer->id) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center">Пока нет сохраненных КП</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            {{ $offers->links() }}
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.offer-row').forEach(function (row) {
                row.addEventListener('click', function (event) {
                    if (event.target.closest('a, button, input, select, textarea, label')) {
                        return;
                    }

                    const href = row.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
            });
        });
    </script>
@endsection
