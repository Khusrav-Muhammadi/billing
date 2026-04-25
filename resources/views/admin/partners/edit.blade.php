@extends('layouts.app')

@section('title')
    Клиенты
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Изменение партнера</h4>
        <a href="#" onclick="history.back();" class="btn btn-outline-danger" style="margin-bottom: 10px">Назад</a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#history" class="btn btn-outline-dark mb-2 ml-2">
            <i class="mdi mdi-history" style="font-size: 30px"></i>
        </a>

        <form method="POST" action="{{ route('partner.update', $partner->id) }}">
            @csrf
            @method('PATCH')

            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $partner->name) }}">
                @error('name')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="phone">Телефон <span class="text-danger">*</span></label>
                <input type="tel" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $partner->phone) }}" required>
                @error('phone')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $partner->email) }}">
                @error('email')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="partner_status_id">Статус партнёра</label>
                <select class="form-control form-control-sm @error('partner_status_id') is-invalid @enderror" name="partner_status_id">
                    @foreach($partnerStatuses as $status)
                        <option value="{{ $status->id }}" {{ old('partner_status_id', $partner->partner_status_id) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                    @endforeach
                </select>
                @error('partner_status_id')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="address">Адрес</label>
                <textarea name="address" cols="30" rows="5" class="form-control @error('address') is-invalid @enderror">{{ old('address', $partner->address) }}</textarea>
                @error('address')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            @php($paymentMethods = old('payment_methods', $partner->payment_methods ?? ['card', 'invoice']))
            <div class="form-group">
                <label>Способы оплаты <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-3">
                    <label class="mb-0">
                        <input type="checkbox" name="payment_methods[]" value="card" {{ in_array('card', (array) $paymentMethods, true) ? 'checked' : '' }}>
                        Карта
                    </label>
                    <label class="mb-0">
                        <input type="checkbox" name="payment_methods[]" value="invoice" {{ in_array('invoice', (array) $paymentMethods, true) ? 'checked' : '' }}>
                        Счет
                    </label>
                    <label class="mb-0">
                        <input type="checkbox" name="payment_methods[]" value="cash" {{ in_array('cash', (array) $paymentMethods, true) ? 'checked' : '' }}>
                        Наличка
                    </label>
                </div>
                @error('payment_methods')
                <span class="text-danger d-block">{{ $message }}</span>
                @enderror
                @error('payment_methods.*')
                <span class="text-danger d-block">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="account_id">Счет партнера</label>
                <select class="form-control @error('account_id') is-invalid @enderror" id="account_id" name="account_id">
                    <option value="">Не выбран</option>
                    @foreach(($accounts ?? collect()) as $account)
                        @php($accountCurrencyCode = strtoupper((string) optional($account->currency)->symbol_code))
                        <option value="{{ $account->id }}" {{ (string) old('account_id', $partner->account_id) === (string) $account->id ? 'selected' : '' }}>
                            {{ $account->name }}{{ $accountCurrencyCode !== '' ? ' (' . $accountCurrencyCode . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('account_id')
                <span class="text-danger d-block">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="currency_id">Валюта партнера <span class="text-danger">*</span></label>
                <select class="form-control @error('currency_id') is-invalid @enderror" id="currency_id" name="currency_id" required>
                    <option value="">Выберите валюту</option>
                    @foreach(($partnerCurrencies ?? collect()) as $currency)
                        @php($currencyCode = strtoupper((string) $currency->symbol_code))
                        <option value="{{ $currency->id }}" {{ (string) old('currency_id', $partner->currency_id) === (string) $currency->id ? 'selected' : '' }}>
                            {{ $currencyCode === 'UZS' ? 'Сум (UZS)' : 'Доллар (USD)' }}
                        </option>
                    @endforeach
                </select>
                @error('currency_id')
                <span class="text-danger d-block">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="sham_link">Ссылка партнера <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('sham_link') is-invalid @enderror" name="sham_link" value="{{ $partner->sham_link }}" required>
                @error('sham_link')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="d-block" for="has_implementation">Наличие внедрения</label>
                <input type="hidden" name="has_implementation" value="0">
                <label class="mb-0">
                    <input type="checkbox" id="has_implementation" name="has_implementation" value="1" {{ old('has_implementation', $partner->has_implementation) ? 'checked' : '' }}>
                    Да
                </label>
                @error('has_implementation')
                <span class="text-danger d-block">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary mr-2">Изменить</button>
        </form>
    </div>

    <div class="modal fade" id="history" tabindex="-1" aria-labelledby="partnerHistoryLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="partnerHistoryLabel">История партнера</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @forelse($partnerHistory as $history)
                        <div style="display: flex; justify-content: space-between;">
                            <h5>{{ $history->status }}</h5>
                            <span>
                                <strong>{{ $history->user?->name ?? 'Система' }}</strong>
                                <i style="font-size: 14px">{{ $history->created_at->format('d.m.Y H:i') }}</i>
                            </span>
                        </div>
                        <div class="ml-3" style="font-size: 14px">
                            @foreach ($history->changes as $change)
                                @php($bodyData = json_decode($change->body, true) ?: [])
                                @foreach ($bodyData as $key => $value)
                                    @if($key === 'name') Название:
                                    @elseif($key === 'email') E-mail:
                                    @elseif($key === 'phone') Телефон:
                                    @elseif($key === 'address') Адрес:
                                    @elseif($key === 'status') Статус:
                                    @elseif($key === 'payment_methods') Способы оплаты:
                                    @elseif($key === 'account_id') Счет партнера:
                                    @elseif($key === 'currency_id') Валюта партнера:
                                    @elseif($key === 'has_implementation') Наличие внедрения:
                                    @elseif($key === 'procent_from_tariff') Процент от подписки:
                                    @elseif($key === 'procent_from_pack') Процент от пакетов:
                                    @elseif($key === 'procent_date') Дата процента:
                                    @elseif($key === 'status_date') Дата статуса:
                                    @else {{ $key }}:
                                    @endif
                                    <p style="margin-left: 20px;">{{ $value['previous_value'] ?? 'N/A' }} ==> {{ $value['new_value'] ?? 'N/A' }}</p>
                                @endforeach
                            @endforeach
                        </div>
                        <hr>
                    @empty
                        <p class="mb-0 text-muted">История пока пустая.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Отступ между формой и таблицей -->
    <div style="margin-top: 40px;"></div>

    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Статусы партнера (Agent / Partner)</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPartnerStatuses">
                Добавить
            </button>
        </div>

        @if($statusHistory && $statusHistory->count() > 0)
            <table class="table table-striped table-hover">
                <thead class="table-light">
                <tr>
                    <th width="5%">№</th>
                    <th width="30%">Дата</th>
                    <th width="30%">Статус</th>
                    <th width="35%">Автор</th>
                </tr>
                </thead>
                <tbody>
                @foreach($statusHistory as $statusRow)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ optional($statusRow->date)->format('d.m.Y') }}</td>
                        <td>{{ (string) $statusRow->status === \App\Enums\PartnerStatusEnum::AGENT->value ? 'Agent' : 'Partner' }}</td>
                        <td>{{ $statusRow->author?->name ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> У этого партнера пока нет истории статусов.
            </div>
        @endif
    </div>

    <!-- Секция менеджеров -->
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Процент партнера</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPartnerProcents">
                Добавить
            </button>
        </div>

        @if($procents && $procents->count() > 0)
            <table class="table table-striped table-hover">
                <thead class="table-light">
                <tr>
                    <th width="5%">№</th>
                    <th width="25%">Дата</th>
                    <th width="20%">Процент от тарифа</th>
                    <th width="20%">Процент от пакета</th>
                    <th width="10%">Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($procents as $procent)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $procent->date }}</td>
                        <td>{{ $procent->procent_from_tariff }}</td>
                        <td>{{ $procent->procent_from_pack }}</td>
                        <td>
                            <div class="btn-group-vertical btn-group-sm">
                                <a data-bs-toggle="modal" data-bs-target="#updateProcent{{$procent->id}}" href="#" class="btn btn-outline-primary btn-sm mb-1">
                                    Изменить
                                </a>

                                <form method="POST" action="{{ route('partner.procent.delete', $procent->id) }}" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        Удалить
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <div class="modal fade" id="updateProcent{{ $procent->id }}" tabindex="-1" aria-labelledby="createManagerLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('partner.procent.edit', $procent->id) }}">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-body">
                                        <div class="form-group mb-3">
                                            <label for="date">Дата <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('date') is-invalid @enderror" name="date" id="date" value="{{ $procent->date }}" required>
                                            @error('date')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="manager_phone">Процент от тарифа <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control @error('procent_from_tariff') is-invalid @enderror" name="procent_from_tariff" id="procent_from_tariff" value="{{ $procent->procent_from_tariff }}" required>
                                            @error('procent_from_tariff')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="manager_email">Процент от пакета <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control @error('procent_from_pack') is-invalid @enderror" name="procent_from_pack" id="procent_from_pack" value="{{ $procent->procent_from_pack }}" required>
                                            @error('procent_from_pack')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-primary">Сохранить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> У этого партнера пока нет статуса.
            </div>
        @endif
    </div>

    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Менеджеры партнера</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createManager">
                Добавить менеджера
            </button>
        </div>

        @if($managers && $managers->count() > 0)
            <table class="table table-striped table-hover">
                <thead class="table-light">
                <tr>
                    <th width="5%">№</th>
                    <th width="25%">ФИО</th>
                    <th width="20%">Телефон</th>
                    <th width="25%">Почта</th>
                    <th width="15%">Ссылка</th>
                    <th width="10%">Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($managers as $manager)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $manager->name }}</td>
                        <td>{{ $manager->phone }}</td>
                        <td>{{ $manager->email }}</td>
                        <td>
                            <a href="https://shamcrm.com/?manager_id={{$manager->id}}" target="_blank" class="text-primary">
                                <small>shamcrm.com/?partner_id={{$manager->id}}</small>
                            </a>
                        </td>
                        <td>
                            <div class="btn-group-vertical btn-group-sm">
                                <a data-bs-toggle="modal" data-bs-target="#updateManager{{$manager->id}}" href="#" class="btn btn-outline-primary btn-sm mb-1">
                                    Изменить
                                </a>

                                <form method="POST" action="{{ route('partner.manager.delete', $manager->id) }}" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этого менеджера?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        Удалить
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <div class="modal fade" id="updateManager{{$manager->id}}" tabindex="-1" aria-labelledby="createManagerLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('partner.manager.update', $manager->id) }}">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="createManagerLabel">Добавить менеджера</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group mb-3">
                                            <label for="manager_name">ФИО <span class="text-danger">*</span></label>
                                            <input  type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="manager_name" placeholder="Введите ФИО менеджера" value="{{$manager->name}}" required>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="manager_password">Пароль <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="manager_password" placeholder="Введите пароль" value="{{ old('password') }}" required>
                                            @error('password')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="manager_phone">Телефон <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control @error('phone') is-invalid @enderror" name="phone" id="manager_phone" placeholder="Введите телефон" value="{{$manager->phone}}" required>
                                            @error('phone')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="manager_email">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="manager_email" placeholder="Введите email" value="{{$manager->email}}" required>
                                            @error('email')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-primary">Сохранить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> У этого партнера пока нет менеджеров.
            </div>
        @endif
    </div>

    <!-- Модальное окно для создания менеджера -->
    <div class="modal fade" id="createManager" tabindex="-1" aria-labelledby="createManagerLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('partner.manager.create', $partner->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createManagerLabel">Добавить менеджера</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label for="manager_name">ФИО <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="manager_name" placeholder="Введите ФИО менеджера" value="{{ old('name') }}" required>
                            @error('name')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="manager_password">Пароль <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="manager_password" placeholder="Введите пароль" value="{{ old('password') }}" required>
                            @error('password')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="manager_phone">Телефон <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control @error('phone') is-invalid @enderror" name="phone" id="manager_phone" placeholder="Введите телефон" value="{{ old('phone') }}" required>
                            @error('phone')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="manager_email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="manager_email" placeholder="Введите email" value="{{ old('email') }}" required>
                            @error('email')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Кураторы партнера</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createCurator">
                Добавить куратора
            </button>
        </div>

        @if(isset($curators) && $curators->count() > 0)
            <table class="table table-striped table-hover">
                <thead class="table-light">
                <tr>
                    <th width="5%">№</th>
                    <th width="35%">ФИО</th>
                    <th width="25%">Телефон</th>
                    <th width="25%">Почта</th>
                    <th width="10%">Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($curators as $curator)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $curator->name }}</td>
                        <td>{{ $curator->phone }}</td>
                        <td>{{ $curator->email }}</td>
                        <td>
                            <form method="POST" action="{{ route('partner.curator.delete', ['partner' => $partner->id, 'curator' => $curator->id]) }}" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этого куратора?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    Удалить
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> У этого партнера пока нет кураторов.
            </div>
        @endif
    </div>

    <div class="modal fade" id="createCurator" tabindex="-1" aria-labelledby="createCuratorLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('partner.curator.create', $partner->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createCuratorLabel">Добавить куратора</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label for="curator_id">Куратор <span class="text-danger">*</span></label>
                            <select class="form-control @error('curator_id') is-invalid @enderror" name="curator_id" id="curator_id" required>
                                <option value="">Не выбран</option>
                                @foreach(($availableCurators ?? collect()) as $availableCurator)
                                    <option value="{{ $availableCurator->id }}">
                                        {{ $availableCurator->name }}{{ $availableCurator->email ? ' (' . $availableCurator->email . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('curator_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        @if(isset($availableCurators) && $availableCurators->count() === 0)
                            <div class="alert alert-warning mb-0">
                                Нет доступных менеджеров для назначения куратором.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary" {{ (isset($availableCurators) && $availableCurators->count() === 0) ? 'disabled' : '' }}>
                            Сохранить
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="createPartnerProcents" tabindex="-1" aria-labelledby="createPartnerProcents" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('partner.procent.create', $partner->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createPartnerProcents">Добавить процент</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label for="date">Дата <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('date') is-invalid @enderror" name="date" id="date" required>
                            @error('date')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="manager_phone">Процент от тарифа <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('procent_from_tariff') is-invalid @enderror" name="procent_from_tariff" id="procent_from_tariff" required>
                            @error('procent_from_tariff')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="manager_email">Процент от пакета <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('procent_from_pack') is-invalid @enderror" name="procent_from_pack" id="procent_from_pack" required>
                            @error('procent_from_pack')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="createPartnerStatuses" tabindex="-1" aria-labelledby="createPartnerStatusesLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('partner.status.create', $partner->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createPartnerStatusesLabel">Добавить статус</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label for="partner_status_date">Дата <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('date') is-invalid @enderror" name="date" id="partner_status_date" value="{{ now()->toDateString() }}" required>
                            @error('date')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label for="partner_status_value">Статус <span class="text-danger">*</span></label>
                            <select class="form-control @error('status') is-invalid @enderror" name="status" id="partner_status_value" required>
                                @foreach(\App\Enums\PartnerStatusEnum::cases() as $statusCase)
                                    <option value="{{ $statusCase->value }}">{{ $statusCase->label() }}</option>
                                @endforeach
                            </select>
                            @error('status')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && request()->has('create_manager'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('createManager'));
                modal.show();
            });
        </script>
    @endif

    @if($errors->has('status') || $errors->has('date'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('createPartnerStatuses'));
                modal.show();
            });
        </script>
    @endif

@endsection
