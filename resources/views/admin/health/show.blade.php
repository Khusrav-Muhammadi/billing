@php
    $fmt = fn ($v) => $v === null ? '—' : (int) round($v);
    $dots = ['red' => '#ef4444', 'yellow' => '#f59e0b', 'grey' => '#9ca3af', 'green' => '#10b981'];
    $chart = array_reverse($rows instanceof \Illuminate\Support\Collection ? $rows->all() : (array) $rows);
@endphp
<x-layouts.admin title="Здоровье: {{ $client['name'] ?? '' }}">
    <div class="mb-4">
        <a href="{{ route('client-health.index') }}" class="text-sm text-brand-600 hover:underline">← Все клиенты</a>
        <h1 class="mt-2 text-xl font-semibold text-ink-900">{{ $client['name'] ?? '' }}</h1>
        <p class="text-sm text-ink-500">
            {{ $client['sub_domain'] ?? '' }}
            @if(!empty($client['crm_url'])) · <a href="{{ $client['crm_url'] }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">CRM</a>@endif
            @if(!empty($client['partner']['name'])) · партнёр: {{ $client['partner']['name'] }}@endif
            @if(!empty($client['manager']['name'])) · менеджер: {{ $client['manager']['name'] }}@endif
        </p>
    </div>

    @if($error)
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $error }}</div>
    @endif

    @if($chart)
        <div class="card mb-5 p-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-ink-500">Индекс по дням</p>
            <div style="display:flex;align-items:flex-end;gap:2px;height:96px">
                @foreach($chart as $d)
                    <div title="{{ $d['date'] }}: {{ $d['health_index'] }}"
                         style="flex:1;min-width:3px;height:{{ max(2, (int) $d['health_index']) }}%;background:{{ $dots[$d['color']] ?? '#9ca3af' }};border-radius:2px 2px 0 0"></div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-ink-400"><span>{{ $chart[0]['date'] }}</span><span>{{ $chart[array_key_last($chart)]['date'] }}</span></div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead class="bg-ink-50">
                <tr><th>Дата</th><th>Цвет</th><th>Индекс</th><th>Входы</th><th>Охват</th><th>Диалоги</th><th>Сущности</th><th>Заходили</th><th>Причина</th></tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                @forelse($rows as $r)
                    <tr>
                        <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d.m.Y') }}</td>
                        <td>@include('admin.health._color', ['color' => $r['color']])</td>
                        <td class="font-semibold">{{ $r['health_index'] }}</td>
                        <td>{{ $fmt($r['scores']['logins'] ?? null) }}</td>
                        <td>{{ $fmt($r['scores']['coverage'] ?? null) }}</td>
                        <td>{{ $fmt($r['scores']['dialogs'] ?? null) }}</td>
                        <td>{{ $fmt($r['scores']['entities'] ?? null) }}</td>
                        <td class="whitespace-nowrap text-xs">{{ $r['metrics']['active_users'] ?? 0 }} из {{ $r['metrics']['users_total'] ?? 0 }}</td>
                        <td class="max-w-[380px] text-xs text-ink-700">
                            {{ $r['reason'] ?: '—' }}
                            @php $notes = collect($r['reasons'] ?? [])->whereIn('code', ['tracking', 'skipped_dialogs', 'skipped_entities'])->pluck('text'); @endphp
                            @if($notes->isNotEmpty())<p class="mt-1 text-ink-400">{{ $notes->implode('; ') }}</p>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-10 text-center text-ink-500">История пока пустая.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
