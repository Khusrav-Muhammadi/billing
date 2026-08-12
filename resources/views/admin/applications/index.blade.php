@extends('layouts.app')

@section('title')
    Коммерческие предложения
@endsection


@section('content')
    <div class="card-body">
        <h4 class="card-title">Оплата</h4>

        <form id="filterForm" method="GET" action="{{ route('application.index') }}">
            <div class="row mb-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Партнер</label>
                    <select name="partner_id" class="form-control">
                        <option value="">Партнер</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" @selected((string)($filters['partner_id'] ?? '') === (string)$partner->id)>
                                {{ $partner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Тип подключения</label>
                    <select name="request_type" class="form-control">
                        <option value="">Тип подключения</option>
                        @foreach($requestTypes as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" @selected(($filters['request_type'] ?? '') === $typeValue)>
                                {{ $typeLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Услуга</label>
                    <select name="tariff_id" class="form-control">
                        <option value="">Услуга</option>
                        @foreach($tariffs as $tariff)
                            <option value="{{ $tariff->id }}" @selected((string)($filters['tariff_id'] ?? '') === (string)$tariff->id)>
                                {{ $tariff->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Период оплаты</label>
                    <select name="period_months" class="form-control">
                        <option value="">Период оплаты</option>
                        <option value="6" @selected((string)($filters['period_months'] ?? '') === '6')>6 мес.</option>
                        <option value="12" @selected((string)($filters['period_months'] ?? '') === '12')>12 мес.</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Статус операции</label>
                    <select name="operation_status" class="form-control">
                        <option value="">Статус операции</option>
                        <option value="draft" @selected(($filters['operation_status'] ?? '') === 'draft')>Черновик</option>
                        <option value="pending" @selected(($filters['operation_status'] ?? '') === 'pending')>В ожидании</option>
                        <option value="paid" @selected(($filters['operation_status'] ?? '') === 'paid')>Оплачено</option>
                        <option value="canceled" @selected(($filters['operation_status'] ?? '') === 'canceled')>Отменено</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Дата от</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2 mt-3">
                    <label class="form-label">Дата до</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-12 mt-3 d-flex gap-2 align-items-center flex-wrap">
                    <button type="submit" class="btn btn-primary">Фильтр</button>
                    <a href="{{ route('application.index') }}" class="btn btn-outline-secondary ms-2">Сбросить</a>
                    <a href="{{ route('application.export.excel', $filterQuery) }}"
                       class="btn btn-outline-success d-inline-flex align-items-center justify-content-center"
                       title="Скачать Excel (оплаченные подключения)"
                       style="width: 42px; height: 38px; padding: 0;">
                        <i class="mdi mdi-file-excel" style="font-size: 22px; line-height: 1;"></i>
                    </a>
                </div>
            </div>
        </form>

        <div class="mb-3">
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle"
                            type="button"
                            id="applicationCreateDropdown"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        Добавить запрос
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="applicationCreateDropdown">
                        <li>
                            <a class="dropdown-item" href="{{ route('application.create.connection') }}">
                                Подключение
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('application.create.connection-extra-services') }}">
                                Подключение доп услуг
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('application.create.renewal') }}">
                                Продление (изменение)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('application.create.renewal-no-changes') }}">
                                Продление
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Клиент</th>
                    <th>Партнер</th>
                    <th>Дата операции</th>
                    <th>Статус операции</th>
                    <th>Тип операции</th>
                    <th>Тип оплаты</th>
                    <th>Тариф</th>
                    <th>Период</th>
                    <th>Сумма оплаты (в валюте)</th>
                    <th>Сумма оплаты (системная валюта)</th>
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
                        $isPartnerPayer = (string) ($offer->payer_type ?? '') === 'partner';

                        $amountInOfferCurrency = (float) ($offer->items->isNotEmpty()
                            ? $offer->items->sum(function ($item) use ($isPartnerPayer) {
                                $lineTotal = (float) ($item->total_price ?? 0);
                                if (!$isPartnerPayer) {
                                    return $lineTotal;
                                }

                                $partnerPercent = max(0, min(100, (float) ($item->partner_percent ?? 0)));
                                return $lineTotal - ($lineTotal * ($partnerPercent / 100));
                            })
                            : (float) $offer->grand_total);
                        $amountInOfferCurrency = max(0, round($amountInOfferCurrency, 2));

                        $amountInSystemCurrency = (float) ($offer->payable_total ?? 0);
                        if ($amountInSystemCurrency <= 0 && $systemCurrencyCode === $amountCurrencyCode) {
                            $amountInSystemCurrency = $amountInOfferCurrency;
                        }
                        $amountInSystemCurrency = max(0, round($amountInSystemCurrency, 2));

                        $formatMoney = static function (float $amount, string $currencyCode): string {
                            $code = strtoupper(trim($currencyCode));
                            $formatted = number_format($amount, 2, ',', ' ');
                            return match ($code) {
                                'USD' => '$' . $formatted,
                                'EUR' => '€' . $formatted,
                                default => $formatted . ' ' . ($code !== '' ? $code : '—'),
                            };
                        };

                        $operationTypeLabel = match ((string) $offer->request_type) {
                            'connection' => 'Подключение',
                            'connection_extra_services' => 'Подключение доп услуг',
                            'renewal' => 'Продление (изменение)',
                            'renewal_no_changes' => 'Продление',
                            default => (string) ($offer->request_type ?: '—'),
                        };
                    @endphp
                    <tr class="offer-row" data-href="{{ route(\App\Http\Controllers\ApplicationController::getRouteNameForOfferType($offer->request_type), $offer) }}" style="cursor: pointer;">
                        <td>{{ $offer->client_name ?: '-' }}</td>
                        <td>{{ $offer->partner_name ?? '-' }}</td>
                        <td>{{ optional($offer->status_date)->format('d.m.Y') }}</td>
                        <td class="offer-status-cell">
                            <span class="badge {{ $operationStatusClass }}">{{ $operationStatusLabel }}</span>
                            <div class="offer-status-actions">
                                <button type="button"
                                        class="offer-status-hint offer-status-view-link"
                                        data-bs-toggle="modal"
                                        data-bs-target="#offerStatusHistoryModal{{ $offer->id }}">
                                    Нажмите для просмотра
                                </button>
                                @if($canManageOfferStatus && $effectiveStatusCode === 'pending')
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm offer-status-confirm-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#offerStatusCreateModal{{ $offer->id }}">
                                        Подтвердить
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td>{{ $operationTypeLabel }}</td>
                        <td>{{ $paymentTypeLabel }}</td>
                        <td>{{ optional($offer->tariff)->name ?? '-' }}</td>
                        <td>{{ $offer->period_months }} мес.</td>
                        <td>{{ $formatMoney($amountInOfferCurrency, $amountCurrencyCode) }}</td>
                        <td>{{ $formatMoney($amountInSystemCurrency, $systemCurrencyCode) }}</td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center">
                            {{ !empty($filterQuery) ? 'По выбранным фильтрам КП не найдены' : 'Пока нет сохраненных КП' }}
                        </td>
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
                $defaultAccountId = optional($offer->offerStatuses->firstWhere('account_id', '!=', null))->account_id
                    ?: ($offer->partner?->account_id ?? null);
            @endphp
            <div class="modal fade" id="offerStatusHistoryModal{{ $offer->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">История статусов подключения #{{ $offer->id }}</h5>
                            <button type="button" class="modal-x-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Закрыть">×</button>
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
                                        <th>Изменить</th>
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
                                            <td>
                                                <button type="button"
                                                        class="btn btn-link p-0 m-0"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#offerStatusEditModal{{ $statusRow->id }}">
                                                    <i class="mdi mdi-pencil-box-outline text-primary" style="font-size: 30px"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">История статусов пока пустая</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            @if($canManageOfferStatus)
                <div class="modal fade" id="offerStatusCreateModal{{ $offer->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Добавить статус подключения #{{ $offer->id }}</h5>
                                <button type="button" class="modal-x-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Закрыть">×</button>
                            </div>
                            <form method="POST" action="{{ route('application.status.store', $offer) }}">
                                @csrf
                                <input type="hidden" name="payment_method" value="{{ $paymentTypeCode }}">

                                <div class="modal-body">
                                    <div class="form-group mb-3">
                                        <label>Статус</label>
                                        <select class="form-control" name="status" required>
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

                                    @if($paymentTypeCode === 'invoice')
                                        <div class="form-group mb-0">
                                            <label>Счет</label>
                                            <select class="form-control" name="account_id" required>
                                                <option value="" {{ $defaultAccountId ? '' : 'selected' }}>Выберите счет</option>
                                                @foreach($accounts as $account)
                                                    @php
                                                        $accountCurrencyCode = strtoupper((string) optional($account->currency)->symbol_code);
                                                    @endphp
                                                    <option value="{{ $account->id }}" {{ (string) $defaultAccountId === (string) $account->id ? 'selected' : '' }}>
                                                        {{ $account->name }}{{ $accountCurrencyCode !== '' ? ' (' . $accountCurrencyCode . ')' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
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

            @foreach($offer->offerStatuses as $statusRow)
                @php
                    $rawPaymentTypeEdit = (string) $statusRow->payment_method;
                    $canManageOfferStatusEdit = in_array($rawPaymentTypeEdit, ['invoice', 'cash'], true) || in_array($paymentTypeCode, ['invoice', 'cash'], true);
                @endphp
                @if($canManageOfferStatusEdit)
                    <div class="modal fade" id="offerStatusEditModal{{ $statusRow->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Изменить статус подключения #{{ $offer->id }}</h5>
                                    <button type="button" class="modal-x-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Закрыть">×</button>
                                </div>
                                <form method="POST" action="{{ route('application.status.update', $statusRow->id) }}">
                                    @csrf
                                    <input type="hidden" name="payment_method" value="{{ $rawPaymentTypeEdit }}">

                                    <div class="modal-body">
                                        <div class="form-group mb-3">
                                            <label>Статус</label>
                                            <select class="form-control" name="status" required>
                                                <option value="pending" {{ $statusRow->status === 'pending' ? 'selected' : '' }}>В ожидании</option>
                                                <option value="paid" {{ $statusRow->status === 'paid' ? 'selected' : '' }}>Оплачено</option>
                                                <option value="canceled" {{ $statusRow->status === 'canceled' ? 'selected' : '' }}>Отменено</option>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Дата</label>
                                            <input type="date" class="form-control" name="status_date" value="{{ optional($statusRow->status_date)->toDateString() }}" required>
                                        </div>

                                        @if($rawPaymentTypeEdit === 'invoice')
                                            <div class="form-group mb-3">
                                                <label>Номер платежки</label>
                                                <input type="text"
                                                       class="form-control"
                                                       name="payment_order_number"
                                                       value="{{ $statusRow->payment_order_number }}"
                                                       placeholder="Введите номер платежки"
                                                       required>
                                            </div>

                                            <div class="form-group mb-0">
                                                <label>Счет</label>
                                                <select class="form-control" name="account_id" required>
                                                    <option value="">Выберите счет</option>
                                                    @foreach($accounts as $account)
                                                        @php
                                                            $accountCurrencyCode = strtoupper((string) optional($account->currency)->symbol_code);
                                                        @endphp
                                                        <option value="{{ $account->id }}" {{ (string) $statusRow->account_id === (string) $account->id ? 'selected' : '' }}>
                                                            {{ $account->name }}{{ $accountCurrencyCode !== '' ? ' (' . $accountCurrencyCode . ')' : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
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

        @endforeach

        <div class="mt-2">
            {{ $offers->links() }}
        </div>
    </div>

    <style>
        .offer-status-cell {
            min-width: 240px;
        }

        .offer-status-hint {
            margin-top: 0;
            font-size: 11px;
            font-weight: 600;
            color: #0d6efd;
            line-height: 1.1;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .offer-status-actions {
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .offer-status-view-link {
            border: 0;
            background: transparent;
            padding: 0;
            text-decoration: none;
            cursor: pointer;
        }

        .offer-status-view-link:hover {
            text-decoration: underline;
        }

        .offer-status-confirm-btn {
            padding: 2px 8px;
            font-size: 11px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .modal-x-close {
            border: 0;
            background: transparent;
            color: #4b5563;
            font-size: 24px;
            line-height: 1;
            padding: 0 2px;
            cursor: pointer;
        }

        .modal-x-close:hover {
            color: #111827;
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
