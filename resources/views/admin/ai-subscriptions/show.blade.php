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
        <p class="text-muted small mb-2">
            Нажмите на период, чтобы увидеть запросы. Время — когда запрос был в CRM (может быть раньше, если логи подтянулись пачкой).
            Цены и суммы — из прайса модели в валюте баланса, без конвертации.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 2rem"></th>
                        <th>Период</th>
                        <th>Итого списано</th>
                        <th>С лимитированного</th>
                        <th>С ИИ-счёта</th>
                        <th>Валюта</th>
                        <th>Записей</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($usageLogs as $u)
                    @php $rawCount = $u->rawLogs->count(); @endphp
                    <tr class="{{ $rawCount > 0 ? '' : 'text-muted' }}"
                        @if($rawCount > 0)
                            style="cursor: pointer"
                            data-bs-toggle="collapse"
                            data-bs-target="#usageDetails{{ $u->id }}"
                            aria-expanded="false"
                        @endif
                    >
                        <td class="text-center">
                            @if($rawCount > 0)
                                <i class="mdi mdi-chevron-down"></i>
                            @endif
                        </td>
                        <td class="small">
                            {{ $u->period_start?->format('d.m.Y H:i') }} —
                            {{ $u->period_end?->format('H:i') }}
                        </td>
                        <td>{{ number_format($u->total_cost, 6) }}</td>
                        <td>{{ number_format($u->deducted_from_limited, 6) }}</td>
                        <td>{{ number_format($u->deducted_from_ai_balance, 6) }}</td>
                        <td>{{ $u->currency?->symbol_code }}</td>
                        <td>{{ $rawCount }}</td>
                    </tr>
                    @if($rawCount > 0)
                        <tr>
                            <td colspan="7" class="p-0 border-0">
                                <div class="collapse" id="usageDetails{{ $u->id }}">
                                    <div class="p-3 bg-light">
                                        @php
                                            $billCurrency = $u->rawLogs->first()?->costCurrency?->symbol_code
                                                ?? $u->currency?->symbol_code
                                                ?? '';
                                        @endphp
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0 bg-white">
                                                <thead>
                                                    <tr>
                                                        <th>Время (CRM)</th>
                                                        <th>Модель</th>
                                                        <th>Вход. токены</th>
                                                        <th>Вых. токены</th>
                                                        <th>Цена вх. /1M <small class="text-muted">{{ $billCurrency }}</small></th>
                                                        <th>Цена вых. /1M <small class="text-muted">{{ $billCurrency }}</small></th>
                                                        <th>Продажа вх. <small class="text-muted">{{ $billCurrency }}</small></th>
                                                        <th>Продажа вых. <small class="text-muted">{{ $billCurrency }}</small></th>
                                                        <th>Продажа <small class="text-muted">{{ $billCurrency }}</small></th>
                                                        <th>Себестоимость <small class="text-muted">{{ $billCurrency }}</small></th>
                                                        <th>Маржа %</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                @foreach($u->rawLogs as $raw)
                                                    <tr>
                                                        <td class="small text-nowrap">
                                                            {{ $raw->created_at?->format('d.m.Y H:i:s') ?? '—' }}
                                                        </td>
                                                        <td><code>{{ $raw->model_name }}</code></td>
                                                        <td>
                                                            {{ number_format($raw->inputTokens()) }}
                                                            @if((int) $raw->prompt_cache_hit_tokens > 0)
                                                                <small class="text-muted">(cache {{ number_format($raw->prompt_cache_hit_tokens) }})</small>
                                                            @endif
                                                        </td>
                                                        <td>{{ number_format((int) $raw->completion_tokens) }}</td>
                                                        <td class="small">{{ number_format((float) $raw->price_per_1m_input_snapshot, 4) }}</td>
                                                        <td class="small">{{ number_format((float) $raw->price_per_1m_output_snapshot, 4) }}</td>
                                                        <td class="text-nowrap small">{{ number_format($raw->sellInputAmount(), 6) }}</td>
                                                        <td class="text-nowrap small">{{ number_format($raw->sellOutputAmount(), 6) }}</td>
                                                        <td class="text-nowrap">{{ number_format($raw->sellAmount(), 6) }}</td>
                                                        <td class="text-nowrap">{{ number_format($raw->costAmount(), 6) }}</td>
                                                        <td>{{ number_format((float) $raw->margin_percent_snapshot, 2) }}%</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr class="fw-semibold">
                                                        <td colspan="2">Итого</td>
                                                        <td>{{ number_format($u->rawLogs->sum(fn ($r) => $r->inputTokens())) }}</td>
                                                        <td>{{ number_format($u->rawLogs->sum('completion_tokens')) }}</td>
                                                        <td class="small text-muted">—</td>
                                                        <td class="small text-muted">—</td>
                                                        <td>{{ number_format($u->rawLogs->sum(fn ($r) => $r->sellInputAmount()), 6) }} {{ $billCurrency }}</td>
                                                        <td>{{ number_format($u->rawLogs->sum(fn ($r) => $r->sellOutputAmount()), 6) }} {{ $billCurrency }}</td>
                                                        <td>{{ number_format($u->rawLogs->sum(fn ($r) => $r->sellAmount()), 6) }} {{ $billCurrency }}</td>
                                                        <td>{{ number_format($u->rawLogs->sum(fn ($r) => $r->costAmount()), 6) }} {{ $billCurrency }}</td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="text-center text-muted">Нет расчётов</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
