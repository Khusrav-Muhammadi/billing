@php
    $fmtPct = function (float $value): string {
        $rounded = round($value, 1);
        return abs($rounded - (int) $rounded) < 0.05
            ? (string) (int) $rounded
            : number_format($rounded, 1, ',', '');
    };
    $fmtDelta = function (?float $delta) use ($fmtPct): string {
        if ($delta === null) {
            return 'нет прошлого дня';
        }
        if ($delta > 0) {
            return '+' . $fmtPct($delta) . ' п.п.';
        }
        if ($delta < 0) {
            return '−' . $fmtPct(abs($delta)) . ' п.п.';
        }

        return '0 п.п.';
    };
    $directionText = ['up' => 'растёт', 'down' => 'падает', 'flat' => 'без изменений'];
    $directionClass = ['up' => 'text-emerald-700', 'down' => 'text-red-700', 'flat' => 'text-ink-500'];
    $barColor = ['up' => '#10b981', 'down' => '#ef4444'];
    $types = ['' => 'Все', 'paid' => 'Платные', 'demo' => 'Демо'];
    $periods = [14 => '14 дней', 30 => '30 дней', 60 => '60 дней'];
    $latest = $latest ?? null;
    $total = (int) ($latest['total'] ?? 0);
    $share = fn (int $count) => $total > 0 ? round($count / $total * 100, 1) : 0.0;
    $parts = [
        ['key' => 'green', 'label' => 'Зелёные', 'dot' => '#10b981', 'count' => (int) ($latest['colors']['green'] ?? 0)],
        ['key' => 'yellow', 'label' => 'Жёлтые', 'dot' => '#f59e0b', 'count' => (int) ($latest['colors']['yellow'] ?? 0)],
        ['key' => 'grey', 'label' => 'Новые', 'dot' => '#9ca3af', 'count' => (int) ($latest['colors']['grey'] ?? 0)],
        ['key' => 'red', 'label' => 'Красные', 'dot' => '#ef4444', 'count' => (int) ($latest['colors']['red'] ?? 0)],
    ];
    $chart = $series;
@endphp
<x-layouts.admin title="Активность клиентов">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Активность клиентов</h1>
            <p class="max-w-2xl text-sm text-ink-500">
                @if($latest)
                    На {{ \Illuminate\Support\Carbon::parse($latest['date'])->format('d.m.Y') }}.
                    Все клиенты этого дня — 100%. Работают — зелёные и жёлтые.
                @else
                    Ночной расчёт ещё не сохранял активность.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @foreach($types as $key => $label)
                <a href="{{ route('client-activity.index', array_filter(['type' => $key, 'days' => $days])) }}"
                   class="btn {{ ($type ?? '') === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
            @endforeach
            <span class="mx-1 hidden h-6 w-px bg-ink-200 sm:block"></span>
            @foreach($periods as $key => $label)
                <a href="{{ route('client-activity.index', array_filter(['type' => $type, 'days' => $key])) }}"
                   class="btn {{ (int) $days === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @if(!$latest)
        <div class="card px-6 py-12 text-center text-sm text-ink-500">
            Когда ночью посчитается светофор, здесь появится доля работающих клиентов и ряд по дням.
        </div>
    @else
        <div class="mb-5 grid gap-3 lg:grid-cols-3">
            <div class="card p-5 lg:col-span-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Работают</p>
                <p class="mt-2 text-4xl font-semibold text-ink-900">{{ $fmtPct((float) $latest['percent']) }}%</p>
                <p class="mt-1 text-sm text-ink-700">{{ $latest['working'] }} из {{ $total }}</p>
                <p class="mt-3 text-sm font-semibold {{ $directionClass[$latest['direction'] ?? ''] ?? 'text-ink-500' }}">
                    {{ $fmtDelta($latest['delta']) }}
                    @if($latest['direction']) · {{ $directionText[$latest['direction']] }} @endif
                </p>
            </div>
            <div class="card p-5 lg:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Из чего складываются 100%</p>
                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-ink-100">
                    @foreach($parts as $part)
                        @if($part['count'] > 0)
                            <div title="{{ $part['label'] }}: {{ $part['count'] }}" style="width: {{ $share($part['count']) }}%; background: {{ $part['dot'] }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach($parts as $part)
                        <a href="{{ route('client-health.index', array_filter(['color' => $part['key'], 'type' => $type])) }}"
                           class="flex items-center justify-between rounded-xl border border-ink-100 px-3 py-2 hover:border-ink-200 hover:bg-ink-50">
                            <span class="flex items-center gap-2 text-sm text-ink-700">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $part['dot'] }}"></span>
                                {{ $part['label'] }}
                            </span>
                            <span class="text-sm font-semibold text-ink-900">{{ $part['count'] }} · {{ $fmtPct($share($part['count'])) }}%</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-5 p-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-ink-500">Доля работающих по дням</p>
            <div style="display:flex;align-items:flex-end;gap:3px;height:120px">
                @foreach($chart as $day)
                    <div title="{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d.m.Y') }}: {{ $fmtPct((float) $day['percent']) }}% ({{ $day['working'] }} из {{ $day['total'] }})"
                         style="flex:1;min-width:4px;height:{{ max(2, (int) round($day['percent'])) }}%;background:{{ $barColor[$day['direction'] ?? ''] ?? '#9ca3af' }};border-radius:2px 2px 0 0"></div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-ink-400">
                <span>{{ \Illuminate\Support\Carbon::parse($chart[0]['date'])->format('d.m.Y') }}</span>
                <span>{{ \Illuminate\Support\Carbon::parse($chart[array_key_last($chart)]['date'])->format('d.m.Y') }}</span>
            </div>
            <p class="mt-3 text-xs text-ink-500">Зелёный столбец — доля выросла к предыдущему дню, красный — упала.</p>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-ink-50">
                    <tr>
                        <th>Дата</th>
                        <th>Клиентов</th>
                        <th>Работают</th>
                        <th>Доля</th>
                        <th>К предыдущему дню</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                    @foreach(array_reverse($series) as $day)
                        <tr>
                            <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d.m.Y') }}</td>
                            <td>{{ $day['total'] }}</td>
                            <td>{{ $day['working'] }}</td>
                            <td class="font-semibold">{{ $fmtPct((float) $day['percent']) }}%</td>
                            <td class="{{ $directionClass[$day['direction'] ?? ''] ?? 'text-ink-500' }}">
                                {{ $fmtDelta($day['delta']) }}
                                @if($day['direction']) · {{ $directionText[$day['direction']] }} @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.admin>
