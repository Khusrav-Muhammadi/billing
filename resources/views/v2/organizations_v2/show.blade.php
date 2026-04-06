@extends('layouts.app')

@section('title')
    Организация - {{ $organization->name }}
@endsection

@section('content')
    <div class="card-body">
        <div class="mb-3">
            <a href="{{ route('organization_v2.index') }}" class="btn btn-outline-danger">Назад</a>
        </div>

        <div class="card mb-4 p-3">
            <h4 class="card-title mb-3">Организация</h4>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <strong>ID:</strong> {{ $organization->order_number ?? $organization->id }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Наименование:</strong> {{ $organization->name }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Телефон:</strong> {{ $organization->phone ?: '-' }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Почта:</strong> {{ $organization->email ?: ($organization->client?->email ?? '-') }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Клиент:</strong> {{ $organization->client?->name ?? '-' }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Партнер:</strong> {{ $organization->client?->partner?->name ?? '-' }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Тариф:</strong> {{ $organization->client?->tariffPrice?->tariff?->name ?? '-' }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Поддомен:</strong> {{ $organization->client?->sub_domain ?? '-' }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Статус:</strong>
                    @if($organization->client?->is_active)
                        <span class="text-success">Активный</span>
                    @else
                        <span class="text-danger">Неактивный</span>
                    @endif
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Последняя активность:</strong>
                    {{ optional($organization->client?->last_activity)->format('d.m.Y H:i') ?: '-' }}
                </div>
                <div class="col-md-3 mb-2">
                    <strong>Баланс:</strong>
                    {{ number_format((float) $realBalance, 2, '.', ' ') }}
                    {{ $organization->client?->country?->currency?->symbol_code ?? '' }}
                </div>
            </div>
        </div>

        <div class="card mb-4 p-3">
            <h4 class="card-title mb-3">Подключенные услуги</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Услуга</th>
                        <th>Валюта услуги</th>
                        <th>Дата подключения</th>
                        <th>Сумма в месяц</th>
                        <th>Активность</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($connectedServices as $service)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $service->tariff?->name ?? ('Услуга #' . $service->tariff_id) }}</td>
                            <td>
                                {{ $service->offerCurrency?->symbol_code ?? $service->offerCurrency?->name ?? ($service->offer_currency_id ? ('ID: ' . $service->offer_currency_id) : '-') }}
                            </td>
                            <td>{{ optional($service->date)->format('d.m.Y H:i') ?: '-' }}</td>
                            <td>{{ number_format((float) $service->service_total_amount, 2, '.', ' ') }}</td>
                            <td>
                                @if($service->status)
                                    <span class="badge badge-success">Активна</span>
                                @else
                                    <span class="badge badge-danger">Неактивна</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Подключенные услуги не найдены</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-3">
            <h4 class="card-title mb-3">Транзакции</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Дата</th>
                        <th>Тип</th>
                        <th>Сумма</th>
                        <th>Валюта</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($balanceOperations as $operation)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ optional($operation->date)->format('d.m.Y H:i') ?: optional($operation->created_at)->format('d.m.Y H:i') }}</td>
                            <td>
                                @if($operation->type === 'income')
                                    <span class="text-success">Пополнение</span>
                                @elseif($operation->type === 'outcome')
                                    <span class="text-danger">Списание</span>
                                @else
                                    {{ $operation->type }}
                                @endif
                            </td>
                            <td>{{ number_format((float) $operation->sum, 4, '.', ' ') }}</td>
                            <td>{{ $operation->currency?->symbol_code ?? $operation->currency?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">Операций баланса не найдено</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
