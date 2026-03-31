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
                    <th>Клиент</th>
                    <th>Партнер</th>
                    <th>Дата операции</th>
                    <th>Тип оплаты</th>
                    <th>Тариф</th>
                    <th>Период</th>
                    <th>Сумма оплаты (в валюте)</th>
                    <th>Сумма оплаты (системная валюта)</th>
                    <th>Статус операции</th>
                </tr>
                </thead>
                <tbody>
                @forelse($offers as $offer)
                    @php
                        $rawPaymentType = strtolower((string) optional($offer->payment)->payment_type);
                        $paymentTypeCode = match ($rawPaymentType) {
                            'alif', 'octo' => 'card',
                            'invoice' => 'invoice',
                            'cash' => 'cash',
                            default => (string) ($offer->latestOfferStatus?->payment_method ?? ''),
                        };
                        $paymentTypeLabel = match ($paymentTypeCode) {
                            'card' => 'Карта',
                            'invoice' => 'Счет',
                            'cash' => 'Наличка',
                            default => '—',
                        };
                        $canManageOfferStatus = in_array($paymentTypeCode, ['invoice', 'cash'], true);

                        $latestStatusCode = (string) ($offer->latestOfferStatus?->status ?? '');
                        $rawOfferStatusCode = (string) ($offer->status ?? '');
                        $effectiveStatusCode = match (true) {
                            in_array($latestStatusCode, ['pending', 'paid', 'canceled'], true) => $latestStatusCode,
                            in_array($rawOfferStatusCode, ['pending', 'paid', 'canceled'], true) => $rawOfferStatusCode,
                            $rawOfferStatusCode === 'payment_link_generated' || $offer->locked_at => 'pending',
                            default => 'draft',
                        };
                        $operationStatusLabel = match ($effectiveStatusCode) {
                            'draft' => 'Черновик',
                            'pending' => 'В ожидании',
                            'paid' => 'Оплачено',
                            'canceled' => 'Отменено',
                            default => 'Черновик',
                        };
                        $operationStatusClass = match ($effectiveStatusCode) {
                            'draft' => 'badge-secondary',
                            'pending' => 'badge-warning',
                            'paid' => 'badge-success',
                            'canceled' => 'badge-danger',
                            default => 'badge-secondary',
                        };

                        $amountCurrencyCode = strtoupper((string) ($offer->currency ?: 'USD'));
                        $systemCurrencyCode = strtoupper((string) ($offer->payable_currency ?: 'USD'));
                    @endphp
                    <tr class="offer-row" data-href="{{ route('application.show', $offer) }}" style="cursor: pointer;">
                        <td>{{ $offer->client_name ?: '-' }}</td>
                        <td>{{ $offer->partner_name ?? '-' }}</td>
                        <td>{{ optional($offer->saved_at)->format('d.m.Y') }}</td>
                        <td>{{ $paymentTypeLabel }}</td>
                        <td>{{ optional($offer->tariff)->name ?? '-' }}</td>
                        <td>{{ $offer->period_months }} мес.</td>
                        <td>{{ number_format((float) $offer->grand_total, 2, '.', ' ') }} {{ $amountCurrencyCode }}</td>
                        <td>{{ number_format((float) $offer->payable_total, 2, '.', ' ') }} {{ $systemCurrencyCode }}</td>
                        <td @if($canManageOfferStatus)
                                class="offer-status-cell offer-status-cell-clickable"
                                role="button"
                                tabindex="0"
                                data-bs-toggle="modal"
                                data-bs-target="#offerStatusHistoryModal{{ $offer->id }}"
                                title="Открыть историю статусов подключения"
                            @endif>
                            <span class="badge {{ $operationStatusClass }}">{{ $operationStatusLabel }}</span>
                            @if($canManageOfferStatus)
                                <div class="offer-status-hint">Нажмите для истории</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">Пока нет сохраненных КП</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @foreach($offers as $offer)
            @php
                $rawPaymentType = strtolower((string) optional($offer->payment)->payment_type);
                $paymentTypeCode = match ($rawPaymentType) {
                    'alif', 'octo' => 'card',
                    'invoice' => 'invoice',
                    'cash' => 'cash',
                    default => (string) ($offer->latestOfferStatus?->payment_method ?? ''),
                };
                $canManageOfferStatus = in_array($paymentTypeCode, ['invoice', 'cash'], true);
            @endphp
            @if($canManageOfferStatus)
                <div class="modal fade" id="offerStatusHistoryModal{{ $offer->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">История статусов подключения #{{ $offer->id }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th>Дата</th>
                                            <th>Статус</th>
                                            <th>Способ</th>
                                            <th>Счет</th>
                                            <th>№ платежки</th>
                                            <th>Автор</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($offer->offerStatuses as $statusRow)
                                            @php
                                                $statusLabel = match ((string) $statusRow->status) {
                                                    'pending' => 'В ожидании',
                                                    'paid' => 'Оплачено',
                                                    'canceled' => 'Отменено',
                                                    default => (string) $statusRow->status,
                                                };
                                                $methodLabel = match ((string) $statusRow->payment_method) {
                                                    'card' => 'Карта',
                                                    'invoice' => 'Счет',
                                                    'cash' => 'Наличка',
                                                    default => (string) $statusRow->payment_method,
                                                };
                                                $accountCurrency = strtoupper((string) optional(optional($statusRow->account)->currency)->symbol_code);
                                                $accountLabel = $statusRow->account
                                                    ? trim($statusRow->account->name . ($accountCurrency !== '' ? ' (' . $accountCurrency . ')' : ''))
                                                    : '-';
                                            @endphp
                                            <tr>
                                                <td>{{ optional($statusRow->status_date)->format('d.m.Y') }}</td>
                                                <td>{{ $statusLabel }}</td>
                                                <td>{{ $methodLabel }}</td>
                                                <td>{{ $accountLabel }}</td>
                                                <td>{{ $statusRow->payment_order_number ?: '-' }}</td>
                                                <td>{{ $statusRow->author?->name ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center">История статусов пока пустая</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="button"
                                            class="btn btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#offerStatusCreateModal{{ $offer->id }}">
                                        Добавить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="offerStatusCreateModal{{ $offer->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Добавить статус подключения #{{ $offer->id }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                            </div>
                            <form method="POST" action="{{ route('application.status.store', $offer) }}">
                                @csrf
                                <input type="hidden" name="payment_method" value="{{ $paymentTypeCode }}">

                                <div class="modal-body">
                                    <div class="form-group mb-3">
                                        <label>Статус</label>
                                        <select class="form-control" name="status" required>
                                            <option value="pending">В ожидании</option>
                                            <option value="paid">Оплачено</option>
                                            <option value="canceled">Отменено</option>
                                        </select>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label>Дата</label>
                                        <input type="date" class="form-control" name="status_date" value="{{ now()->toDateString() }}" required>
                                    </div>

                                    @if($paymentTypeCode === 'invoice')
                                        <div class="form-group mb-3">
                                            <label>Номер платежки</label>
                                            <input type="text"
                                                   class="form-control"
                                                   name="payment_order_number"
                                                   placeholder="Введите номер платежки"
                                                   required>
                                        </div>
                                    @endif

                                    <div class="form-group mb-0">
                                        <label>Счет</label>
                                        <select class="form-control" name="account_id" required>
                                            <option value="">Выберите счет</option>
                                            @foreach($accounts as $account)
                                                @php
                                                    $accountCurrencyCode = strtoupper((string) optional($account->currency)->symbol_code);
                                                @endphp
                                                <option value="{{ $account->id }}">
                                                    {{ $account->name }}{{ $accountCurrencyCode !== '' ? ' (' . $accountCurrencyCode . ')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="submit" class="btn btn-primary">Сохранить</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        <div class="mt-2">
            {{ $offers->links() }}
        </div>
    </div>

    <style>
        .offer-status-cell-clickable {
            cursor: pointer;
            min-width: 140px;
        }

        .offer-status-cell-clickable .badge {
            border: 1px solid #0d6efd;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.08);
            transition: all .15s ease-in-out;
        }

        .offer-status-cell-clickable:hover .badge {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.18);
            transform: translateY(-1px);
        }

        .offer-status-hint {
            margin-top: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #0d6efd;
            line-height: 1.1;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.offer-row').forEach(function (row) {
                row.addEventListener('click', function (event) {
                    if (event.target.closest('a, button, input, select, textarea, label')) {
                        return;
                    }
                    if (event.target.closest('.offer-status-cell')) {
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
