@extends('layouts.admin')

@section('title')
    Организация
@endsection


@section('content')

    @isset($health)
        @include('admin.health.blocks')
    @endisset

    @php
        $renewalUntil = now()->addDays(30)->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $kept = array_filter(['health' => request('health')]);
        $expiryQuick = [
            [
                'label' => 'Истекает за 30 дней',
                'until' => $renewalUntil,
                'hint' => 'Подключённые клиенты, у которых оплата заканчивается в ближайшие 30 дней (по ' . now()->addDays(30)->format('d.m.Y') . '). Сначала те, у кого срок ближе.',
            ],
            [
                'label' => 'Истекает в этом месяце',
                'until' => $monthEnd,
                'hint' => 'Подключённые клиенты, у которых оплата заканчивается до конца месяца (по ' . now()->endOfMonth()->format('d.m.Y') . '). Сначала те, у кого срок ближе.',
            ],
        ];
    @endphp
    <form id="filterForm" method="GET" action="{{ route('organization_v2.index') }}" class="mb-3">
        @if(filled(request('health')))
            <input type="hidden" name="health" value="{{ request('health') }}">
        @endif
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div style="width: 150px;">
                <label class="form-label">Статус</label>
                <select name="status" class="form-control">
                    <option value="">Все</option>
                    <option value="1" @selected(request('status') === '1')>Активный</option>
                    <option value="0" @selected(request('status') === '0')>Неактивный</option>
                </select>
            </div>
            <div style="width: 160px;">
                <label class="form-label">Тариф</label>
                <select name="tariff" class="form-control">
                    <option value="">Все</option>
                    @foreach($tariffs as $tariff)
                        <option value="{{ $tariff->id }}" @selected((string)request('tariff') === (string)$tariff->id)>{{ $tariff->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="width: 160px;">
                <label class="form-label">Страна</label>
                <select name="country" class="form-control">
                    <option value="">Все</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" @selected((string)request('country') === (string)$country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="width: 180px;">
                <label class="form-label">Партнер</label>
                <select name="partner" class="form-control">
                    <option value="">Все</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}" @selected((string)request('partner') === (string)$partner->id)>{{ $partner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="width: 170px;">
                <label class="form-label">Срок до</label>
                <input type="date" name="valid_until_to" class="form-control" value="{{ request('valid_until_to') }}">
            </div>
            <div style="width: 200px; flex: 1 1 180px;">
                <label class="form-label">Поиск</label>
                <input type="text" name="search" class="form-control" placeholder="Имя, телефон, почта" value="{{ request('search') }}">
            </div>
            <button type="submit" class="btn btn-primary">Фильтр</button>
            <a href="{{ route('organization_v2.index', $kept) }}" class="btn btn-outline-secondary">Сбросить</a>
            @foreach($expiryQuick as $quick)
                @php $active = request('valid_until_to') === $quick['until']; @endphp
                <a href="{{ $active ? route('organization_v2.index', $kept) : route('organization_v2.index', $kept + ['valid_until_to' => $quick['until'], 'sort' => 'valid_until', 'direction' => 'asc']) }}"
                   class="btn {{ $active ? 'btn-primary' : 'btn-outline-secondary' }}"
                   title="{{ $quick['hint'] }}">{{ $quick['label'] }}@if($active) ✕@endif</a>
            @endforeach
        </div>
    </form>
	    <div class="card-body">
	        <h4 class="card-title">Клиенты</h4>
	        <div class="table-responsive">
	            @include('admin.partials.organizations_v2', ['organizations' => $organizations, 'healthByClient' => $healthByClient ?? []])
	        </div>
	    </div>

@endsection


@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('click', event => {
                const row = event.target.closest('.organization-row');
                if (!row) {
                    return;
                }

                if (event.target.closest('a, button, input, select, textarea, label, .modal')) {
                    return;
                }

                const href = row.dataset.href;
                if (href) {
                    window.location.href = href;
                }
            });
        });


    </script>
@endsection
