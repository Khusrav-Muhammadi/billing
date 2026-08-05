@extends('layouts.app')

@section('title') ИИ-Подписка #{{ $aiSubscription->id }} @endsection

@section('content')
<div class="card-body">
    <a href="{{ route('ai-subscription.index') }}" class="btn btn-outline-secondary btn-sm mb-3">← Назад</a>

    <div class="row g-3">

        {{-- Подписка --}}
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5 class="card-title">Подписка</h5>
                <table class="table table-sm mb-0">
                    <tr><th>Организация</th><td>{{ $aiSubscription->organization?->name }}</td></tr>
                    <tr><th>Тарифный план</th><td>{{ $aiSubscription->plan?->name }}</td></tr>
                    <tr><th>Период</th><td>{{ $aiSubscription->period_months }} мес.</td></tr>
                    <tr><th>Оплачено</th><td>{{ number_format($aiSubscription->price_paid, 2) }}</td></tr>
                    <tr><th>Статус</th><td>
                        @if($aiSubscription->status)
                            <span class="badge bg-success">Активна</span>
                        @else
                            <span class="badge bg-secondary">Неактивна</span>
                        @endif
                    </td></tr>
                    <tr><th>Начало</th><td>{{ $aiSubscription->started_at?->format('d.m.Y H:i') }}</td></tr>
                    <tr><th>Окончание</th><td>{{ $aiSubscription->expires_at?->format('d.m.Y H:i') }}</td></tr>
                    <tr><th>Последний фетч CRM</th><td>{{ $aiSubscription->last_crm_fetch_at?->format('d.m.Y H:i') ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        {{-- Баланс --}}
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5 class="card-title">Баланс ИИ</h5>
                @if($balance)
                    <table class="table table-sm mb-0">
                        <tr><th>Лимитированный</th>
                            <td class="{{ (float)$balance->limited_balance < 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($balance->limited_balance, 4) }} {{ $balance->currency?->symbol_code }}
                            </td>
                        </tr>
                        <tr><th>ИИ-счёт</th>
                            <td class="{{ (float)$balance->ai_balance < 0 ? 'text-danger' : '' }}">
                                {{ number_format($balance->ai_balance, 4) }} {{ $balance->currency?->symbol_code }}
                            </td>
                        </tr>
                        <tr><th>Итого</th>
                            <td><strong>{{ number_format($balance->totalBalance(), 4) }} {{ $balance->currency?->symbol_code }}</strong></td>
                        </tr>
                        <tr><th>Агент</th>
                            <td>
                                @if($balance->is_agent_enabled)
                                    <span class="badge bg-success">Включён</span>
                                @else
                                    <span class="badge bg-secondary">Выключен</span>
                                @endif
                            </td>
                        </tr>
                        @if($balance->scheduled_activation_at)
                        <tr><th>Отложенная активация</th>
                            <td>{{ $balance->scheduled_activation_at->format('d.m.Y') }}</td>
                        </tr>
                        @endif
                    </table>
                @else
                    <p class="text-muted">Баланс не создан</p>
                @endif
            </div>
        </div>
    </div>

    {{-- История транзакций --}}
    <div class="card mt-3 p-3">
        <h5 class="card-title">История движения баланса</h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Тип</th>
                        <th>Счёт</th>
                        <th>Сумма</th>
                        <th>Описание</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($transactions as $t)
                    @php
                        $typeColors = [
                            'topup' => 'success',
                            'payment' => 'success',
                            'monthly_purchase' => 'warning',
                            'tariff_grant' => 'primary',
                            'tariff_grant_prorated' => 'info',
                            'deduction' => 'warning',
                            'overdraft_cover' => 'danger',
                            'expired_profit' => 'secondary',
                            'debt_cover' => 'dark',
                            'reversal' => 'danger',
                        ];
                        $typeLabels = [
                            'topup' => 'Пополнение',
                            'payment' => 'Оплата тарифа',
                            'monthly_purchase' => 'Покупка лимита',
                            'tariff_grant' => 'Начисление лимита',
                            'tariff_grant_prorated' => 'Пропорц. начисление лимита',
                            'deduction' => 'Списание за использование',
                            'overdraft_cover' => 'Покрытие овердрафта',
                            'expired_profit' => 'Сгорание лимита',
                            'debt_cover' => 'Покрытие долга',
                            'reversal' => 'Отмена / возврат',
                        ];
                        $accountLabels = [
                            'limited' => 'Лимит',
                            'ai_balance' => 'Кошелёк ИИ',
                        ];
                    @endphp
                    <tr>
                        <td class="small">{{ $t->created_at?->format('d.m.Y H:i:s') }}</td>
                        <td>
                            <span class="badge bg-{{ $typeColors[$t->type] ?? 'secondary' }}">
                                {{ $typeLabels[$t->type] ?? $t->type }}
                            </span>
                        </td>
                        <td>{{ $accountLabels[$t->target_balance] ?? $t->target_balance }}</td>
                        <td>{{ number_format($t->amount, 4) }} {{ $t->currency?->symbol_code }}</td>
                        <td class="small text-muted">{{ $t->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Нет операций</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- История 30-мин расчётов --}}
    <div class="card mt-3 p-3">
        <h5 class="card-title">30-минутные расчёты</h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Период</th>
                        <th>Итого списано</th>
                        <th>С лимитированного</th>
                        <th>С ИИ-счёта</th>
                        <th>Валюта</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($usageLogs as $u)
                    <tr>
                        <td class="small">
                            {{ $u->period_start?->format('d.m.Y H:i') }} —
                            {{ $u->period_end?->format('H:i') }}
                        </td>
                        <td>{{ number_format($u->total_cost, 6) }}</td>
                        <td>{{ number_format($u->deducted_from_limited, 6) }}</td>
                        <td>{{ number_format($u->deducted_from_ai_balance, 6) }}</td>
                        <td>{{ $u->currency?->symbol_code }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Нет расчётов</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
