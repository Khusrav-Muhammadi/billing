@php
    $tabs = ['' => 'Все', 'red' => 'Красные', 'yellow' => 'Жёлтые', 'grey' => 'Новые', 'green' => 'Зелёные'];
    $total = array_sum($counts);
    $fmt = fn ($v) => $v === null ? '—' : (int) round($v);
@endphp
<x-layouts.admin title="Здоровье клиентов">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Здоровье клиентов</h1>
            <p class="text-sm text-ink-500">
                @if($date) Данные на {{ \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') }}, считаются каждую ночь за последние 7 дней. @else Расчёт ещё не запускался. @endif
            </p>
        </div>
        <form method="GET" action="{{ route('client-health.index') }}" class="flex flex-wrap items-center gap-2">
            @if($color)<input type="hidden" name="color" value="{{ $color }}">@endif
            <select name="partner" class="input w-auto max-w-[220px] py-2">
                <option value="">Все клиенты</option>
                <option value="any" @selected($partner === 'any')>Только клиенты партнёров</option>
                @foreach($partnerOptions as $id => $name)
                    <option value="{{ $id }}" @selected((string) $partner === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <input type="search" name="search" value="{{ $search }}" placeholder="Клиент или поддомен" class="input w-56 py-2">
            <select name="type" class="input w-auto py-2">
                <option value="">Платные и демо</option>
                <option value="paid" @selected($type === 'paid')>Только платные</option>
                <option value="demo" @selected($type === 'demo')>Только демо</option>
            </select>
            <button class="btn btn-secondary px-4 py-2">Найти</button>
        </form>
    </div>

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach($tabs as $key => $label)
            @php $n = $key === '' ? $total : ($counts[$key] ?? 0); @endphp
            <a href="{{ route('client-health.index', array_filter(['color' => $key, 'type' => $type, 'search' => $search, 'partner' => $partner])) }}"
               class="btn {{ ($color ?? '') === $key ? 'btn-primary' : 'btn-secondary' }}">
                @if($key !== '')@include('admin.health._color', ['color' => $key])@endif
                <span>{{ $key === '' ? $label : '' }} {{ $n }}</span>
            </a>
        @endforeach
    </div>

    @if($error)
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $error }}</div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead class="bg-ink-50">
                <tr><th>Клиент</th><th>Цвет</th><th>Индекс</th><th>Причина</th><th>Оплачено до</th><th>Ответственный</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                @forelse($rows as $r)
                    @php $orgId = $orgByClient[$r['client_id']] ?? null; @endphp
                    <tr>
                        <td class="min-w-[180px]">
                            <a href="{{ $orgId ? route('organization_v2.show', $orgId) : route('sub_domain.show', $r['client_id']) }}" class="font-semibold hover:text-brand-700 hover:underline">{{ $r['name'] ?? ('#' . $r['client_id']) }}</a>
                            <p class="text-xs text-ink-500">{{ $r['sub_domain'] }}@if(($r['client_type'] ?? '') === 'demo') · <span class="badge bg-amber-50 text-amber-700">демо</span>@endif</p>
                        </td>
                        <td>@include('admin.health._color', ['color' => $r['color']])</td>
                        <td class="whitespace-nowrap">
                            <p class="text-lg font-semibold text-ink-900">{{ $r['health_index'] }}</p>
                            <p class="text-xs text-ink-500" title="Входы · Охват · Диалоги · Сущности">
                                В {{ $fmt($r['scores']['logins'] ?? null) }} · О {{ $fmt($r['scores']['coverage'] ?? null) }} · Д {{ $fmt($r['scores']['dialogs'] ?? null) }} · С {{ $fmt($r['scores']['entities'] ?? null) }}
                            </p>
                        </td>
                        <td class="max-w-[360px] text-xs text-ink-700">{{ $r['reason'] ?: '—' }}</td>
                        <td class="whitespace-nowrap text-xs">
                            @if($r['paid_until'])
                                <p class="font-semibold">{{ \Illuminate\Support\Carbon::parse($r['paid_until'])->format('d.m.Y') }}</p>
                                @if($r['days_left'] !== null)
                                    <p class="{{ $r['days_left'] <= 30 ? 'text-red-700' : 'text-ink-500' }}">{{ $r['days_left'] >= 0 ? 'через ' . $r['days_left'] . ' дн.' : 'закончилась' }}</p>
                                @endif
                            @else — @endif
                        </td>
                        <td class="text-xs">
                            @if(!empty($r['partner']['id']))
                                <a href="{{ route('partner.edit', $r['partner']['id']) }}" class="hover:text-brand-700 hover:underline">{{ $r['partner']['name'] }}</a>
                            @else
                                <p>—</p>
                            @endif
                            @if(!empty($r['manager']['name']))<p class="text-ink-500">менеджер: {{ $r['manager']['name'] }}</p>@endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('client-health.show', $r['client_id']) }}" class="text-sm text-brand-600 hover:underline">История</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-ink-500">Нет клиентов с таким фильтром.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>

    <div class="mt-4 space-y-1 text-xs text-ink-500">
        <p>Индекс 0–100, главное — работа в CRM: диалоги 35% и сущности 40% — новые лиды, сделки, заказы, товары и смены этапа (неделя против обычной: медиана 4 прошлых недель); входы 15% (дней из 7, когда работал хоть кто-то, 5 и больше = 100) и охват 10% (30% пользователей и больше = 100). Пункт не считается, если клиент им почти не пользуется (не было в большинстве недель или меньше 10% работы), его вес делится на остальные.</p>
        <p>Зелёный — 70+. Жёлтый — 40–69 или падение на 20+ за 2 недели. Красный — ниже 40, 7 дней никто не заходил, жёлтый при оплате на 30 дней и меньше или CRM клиента недоступна. Новый (серый) — первые 28 дней после оплаты; «никто не заходил» и недоступная CRM остаются красными и у новых.</p>
    </div>
</x-layouts.admin>
