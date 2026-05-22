@extends('layouts.app')

@section('title')
    Организация - {{ $organization->name }}
@endsection

@section('content')
    <div class="card-body">
        <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('organization_v2.index') }}" class="btn btn-outline-danger">Назад</a>

            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle"
                        type="button"
                        id="organizationApplicationCreateDropdown"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    Подключение
                </button>
                <ul class="dropdown-menu" aria-labelledby="organizationApplicationCreateDropdown">
                    <li>
                        <a class="dropdown-item"
                           href="{{ route('application.create.connection', ['organization_id' => $organization->id]) }}">
                            Подключение
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item"
                           href="{{ route('application.create.connection-extra-services', ['organization_id' => $organization->id]) }}">
                            Подключение доп услуг
                        </a>
                    </li>
                    <li>

                        <a class="dropdown-item"
                           href="{{ route('application.create.renewal', ['organization_id' => $organization->id]) }}">
                            Продление (изменение)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item"
                           href="{{ route('application.create.renewal-no-changes', ['organization_id' => $organization->id]) }}">
                            Продление
                        </a>
                    </li>
                </ul>
            </div>
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
                    @if(optional($organization->connections()->latest('status_date')->latest('id')->first())->status === 'connected')
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
                        <th>Количество</th>
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
                            <td>{{ $service->quantity ?? 1 }}</td>
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
                                    <a href="{{ route(\App\Http\Controllers\ApplicationController::getRouteNameForOfferType($row->commercialOffer->request_type ?? 'connection'), $row->commercial_offer_id) }}" target="_blank" class="text-primary">
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

        <div class="card mt-4 p-3">
            <h4 class="card-title mb-3">API запросы и почты</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Дата</th>
                        <th>Тип</th>
                        <th>Действие</th>
                        <th>Куда</th>
                        <th>Статус</th>
                        <th>Детали</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse(($integrationLogs ?? collect()) as $log)
                        @php
                            $typeLabel = $log->type === 'email' ? 'Почта' : 'API';
                            $actionLabel = [
                                'create_subdomain' => 'Создание поддомена',
                                'send_to_sham' => 'Заявка в Sham API',
                                'demo_welcome_email' => 'Демо-письмо',
                                'create_organization' => 'Создание организации',
                                'site_access_email' => 'Письмо с доступом',
                                'demo_expired_followup_3' => 'Демо: письмо через 3 дня',
                                'demo_expired_followup_6' => 'Демо: письмо через 6 дней',
                                'demo_expired_followup_14' => 'Демо: письмо через 14 дней',
                                'demo_expired_followup_18' => 'Демо: письмо через 18 дней',
                                'commercial_offer_paid_email' => 'Письмо после оплаты',
                                'client_payment_invoice_email' => 'Отправка счета на почту',
                                'low_balance_email' => 'Уведомление о балансе',
                                'update_tariff' => 'Изменение тарифа',
                                'connection_update_tariff' => 'Подключение тарифа',
                                'add_pack' => 'Добавление доп. услуг',
                                'activation' => 'Активация',
                                'deactivation' => 'Отключение',
                                'tariff_extension' => 'Продление доступа',
                                'disable_demo' => 'Отключение демо',
                            ][$log->action] ?? ($log->action ?: '-');
                            $target = $log->type === 'email'
                                ? ($log->recipient ?: '-')
                                : trim(($log->method ? $log->method . ' ' : '') . ($log->url ?: '-'));
                            $emailHtml = $log->type === 'email'
                                ? (data_get($log->payload, 'email_body.html') ?: data_get($log->payload, 'request_body.html'))
                                : null;
                            $emailText = $log->type === 'email'
                                ? (data_get($log->payload, 'email_body.text') ?: data_get($log->payload, 'request_body.text'))
                                : null;
                            $payloadJson = json_encode($log->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $responseJson = json_encode($log->response ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $canRetry = $log->successful === false || ($log->status_code && !in_array((int) $log->status_code, [200, 201], true));
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ optional($log->occurred_at)->format('d.m.Y H:i:s') ?: '-' }}</td>
                            <td>{{ $typeLabel }}</td>
                            <td>{{ $actionLabel }}</td>
                            <td style="max-width:360px; word-break:break-word;">{{ $target }}</td>
                            <td>
                                @if($log->successful === true)
                                    <span class="badge badge-success">Успешно</span>
                                @elseif($log->successful === false)
                                    <span class="badge badge-danger">Ошибка</span>
                                @else
                                    <span class="badge badge-secondary">—</span>
                                @endif
                                @if($log->status_code)
                                    <span class="text-muted">({{ $log->status_code }})</span>
                                @endif
                            </td>
                            <td>
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#integrationLogModal{{ $log->id }}">
                                    Просмотр
                                </button>
                                @if($canRetry)
                                    <form action="{{ route('organization_v2.integration-log.retry', $log) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Повторить этот запрос с тем же телом?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Повторить
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>

                        <div class="modal fade" id="integrationLogModal{{ $log->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ $typeLabel }} — {{ $actionLabel }}</h5>
                                        <button type="button" class="modal-x-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Закрыть">×</button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <strong>Дата:</strong> {{ optional($log->occurred_at)->format('d.m.Y H:i:s') ?: '-' }}<br>
                                            <strong>Куда:</strong> {{ $target }}<br>
                                            @if($log->subject)
                                                <strong>Тема:</strong> {{ $log->subject }}<br>
                                            @endif
                                            @if($log->error)
                                                <strong class="text-danger">Ошибка:</strong> {{ $log->error }}
                                            @endif
                                        </div>

                                        @if($log->type === 'email' && $emailHtml)
                                            <h6>Превью письма</h6>
                                            <iframe
                                                title="Превью письма"
                                                sandbox=""
                                                srcdoc="{{ $emailHtml }}"
                                                style="width:100%; min-height:520px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; margin-bottom:16px;"></iframe>

                                            <h6>HTML body письма</h6>
                                            <pre style="white-space:pre-wrap; background:#f8f9fa; border:1px solid #e5e7eb; border-radius:6px; padding:12px; max-height:420px; overflow:auto;">{{ $emailHtml }}</pre>

                                            @if($emailText)
                                                <h6>Text body письма</h6>
                                                <pre style="white-space:pre-wrap; background:#f8f9fa; border:1px solid #e5e7eb; border-radius:6px; padding:12px; max-height:240px; overflow:auto;">{{ $emailText }}</pre>
                                            @endif

                                            <h6>JSON данные / запрос к Resend</h6>
                                        @else
                                            <h6>Тело запроса / данные письма</h6>
                                        @endif

                                        <pre style="white-space:pre-wrap; background:#f8f9fa; border:1px solid #e5e7eb; border-radius:6px; padding:12px; max-height:360px; overflow:auto;">{{ $payloadJson ?: '{}' }}</pre>

                                        @if($log->type === 'api')
                                            <h6>Ответ</h6>
                                            <pre style="white-space:pre-wrap; background:#f8f9fa; border:1px solid #e5e7eb; border-radius:6px; padding:12px; max-height:360px; overflow:auto;">{{ $responseJson ?: '{}' }}</pre>
                                        @endif
                                    </div>
                                    @if($canRetry)
                                        <div class="modal-footer">
                                            <form action="{{ route('organization_v2.integration-log.retry', $log) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Повторить этот запрос с тем же телом?')">
                                                @csrf
                                                <button type="submit" class="btn btn-danger">
                                                    Повторить
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">API запросы и письма пока не логировались</td>
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
