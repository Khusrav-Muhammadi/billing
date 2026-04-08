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
                        <th>Дата отключения</th>
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
                            <td>
                                @if($service->status)
                                    —
                                @else
                                    {{ optional($service->deactivated_at ?? $service->updated_at)->format('d.m.Y H:i') ?: '-' }}
                                @endif
                            </td>
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
                            <td colspan="7" class="text-center">Подключенные услуги не найдены</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4 p-3">
            <h4 class="card-title mb-3">История подключения</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Дата</th>
                        <th>Статус</th>
                        <th>Документ</th>
                        <th>Автор</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse(($connectionStatusHistory ?? collect()) as $row)
                        @php
                            $reason = (string) ($row->reason ?? '');
                            $reasonLabel = [
                                'connection' => 'Подключение',
                                'renewal' => 'Продление',
                                'renewal_no_changes' => 'Продление без изменений',
                                'insufficient_balance' => 'Недостаточно баланса',
                            ][$reason] ?? ($reason !== '' ? $reason : '—');
                        @endphp






                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ optional($row->status_date)->format('d.m.Y H:i') ?: '-' }}</td>
                            <td>
                                @if((string) $row->status === 'connected')
                                    <span class="badge badge-success">Подключено</span>
                                @else
                                    <span class="badge badge-danger">Отключено</span>
                                @endif
                            </td>
                            <td>
                                @if($row->commercial_offer_id)
                                    <a href="{{ route('application.show', $row->commercial_offer_id) }}" target="_blank" class="text-primary">
                                        КП #{{ $row->commercial_offer_id }}
                                    </a>
                                @elseif($row->day_closing_id)
                                    <a href="{{ route('day-closing.show', $row->day_closing_id) }}" target="_blank" class="text-primary">
                                        Закрытие #{{ $row->dayClosing?->doc_number ?? $row->day_closing_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $row->author?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">История подключения не найдена</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-3">
            <h4 class="card-title mb-3">Транзакции</h4>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 12px;">
                <button type="button" class="btn btn-sm btn-outline-secondary active" data-transaction-filter="all" aria-pressed="true">Все</button>
                <button type="button" class="btn btn-sm btn-outline-success" data-transaction-filter="income" aria-pressed="false">Пополнения</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-transaction-filter="outcome" aria-pressed="false">Списания</button>
            </div>
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
                        <tr data-transaction-type="{{ $operation->type }}">
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

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buttons = Array.from(document.querySelectorAll('[data-transaction-filter]'));
            const rows = Array.from(document.querySelectorAll('tr[data-transaction-type]'));
            if (!buttons.length || !rows.length) return;

            const setActiveButton = (activeBtn) => {
                buttons.forEach((btn) => {
                    const isActive = btn === activeBtn;
                    btn.classList.toggle('active', isActive);
                    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            const applyFilter = (filter) => {
                const normalized = String(filter || 'all');
                rows.forEach((row) => {
                    const type = String(row.getAttribute('data-transaction-type') || '');
                    const shouldShow = normalized === 'all' || type === normalized;
                    row.style.display = shouldShow ? '' : 'none';
                });
            };

            buttons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const filter = btn.getAttribute('data-transaction-filter') || 'all';
                    setActiveButton(btn);
                    applyFilter(filter);
                });
            });

            const active = buttons.find((b) => b.classList.contains('active')) || buttons[0];
            setActiveButton(active);
            applyFilter(active.getAttribute('data-transaction-filter') || 'all');
        });
    </script>
@endsection
