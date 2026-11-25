{{--
    Плитки над списком клиентов: Все, цвета светофора и Отключенные.
    Клик фильтрует основную таблицу (?health=red|yellow|grey|green|off), повторный клик снимает фильтр.
--}}
@php
    $forDemo = !empty($forDemo);
    $blocks = [
        'all' => ['label' => 'Все', 'hint' => $forDemo ? 'все демо-аккаунты' : 'все подключённые клиенты', 'dot' => '#0b0efd', 'count' => $health['totals']['all'] ?? null],
        'red' => ['label' => 'Красные', 'hint' => $forDemo ? 'с 3-го дня никто не входил, или срок кончается без работы' : 'нужна помощь сейчас', 'dot' => '#ef4444', 'count' => $health['counts']['red'] ?? 0, 'help' => true],
        'yellow' => ['label' => 'Жёлтые', 'hint' => $forDemo ? 'входили, но за 14 дней нет работы в CRM' : 'активность падает', 'dot' => '#f59e0b', 'count' => $health['counts']['yellow'] ?? 0, 'help' => true],
        'grey' => ['label' => $forDemo ? 'Серые' : 'Новые', 'hint' => $forDemo ? 'первые 2 дня демо' : 'первые 28 дней после оплаты', 'dot' => '#9ca3af', 'count' => $health['counts']['grey'] ?? 0, 'help' => true],
        'green' => ['label' => 'Зелёные', 'hint' => $forDemo ? 'входили и есть работа в CRM' : 'всё в норме', 'dot' => '#10b981', 'count' => $health['counts']['green'] ?? 0, 'help' => true],
        'off' => ['label' => $forDemo ? 'Истёкшие' : 'Отключенные', 'hint' => $forDemo ? 'демо старше 14 дней' : 'подключение остановлено', 'dot' => '#64748b', 'count' => $health['totals']['disconnected'] ?? null],
    ];
    $active = $health['color'] ?? null;
@endphp
<div x-data="{ help: null }" class="mb-3">
    <div class="grid grid-cols-3 gap-2 md:grid-cols-6">
        @foreach($blocks as $key => $b)
            @php $isActive = $key === 'all' ? $active === null : $active === $key; @endphp
            <div class="relative rounded-xl border bg-white transition hover:shadow-md {{ $isActive ? '' : 'border-ink-200' }}"
                 style="{{ $isActive ? 'border-color:' . $b['dot'] . ';box-shadow:0 0 0 1px ' . $b['dot'] : '' }}">
                <a href="{{ request()->fullUrlWithQuery(['health' => ($key === 'all' || $isActive) ? null : $key, 'page' => null]) }}"
                   class="block px-3 py-2" title="{{ $b['hint'] }}">
                    <p class="flex items-center gap-1.5 text-xs font-medium text-ink-500"><span style="display:inline-block;width:8px;height:8px;border-radius:9999px;background:{{ $b['dot'] }}"></span>{{ $b['label'] }}</p>
                    <p class="text-xl font-semibold leading-tight text-ink-900">{{ $b['count'] === null ? '—' : (int) $b['count'] }}</p>
                </a>
                @if(!empty($b['help']))
                    <button type="button" @click="help = '{{ $key }}'"
                            class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full border border-ink-200 bg-white text-xs font-semibold text-ink-500 hover:border-ink-300 hover:text-ink-900"
                            title="Что значит «{{ $b['label'] }}»" aria-label="Что значит «{{ $b['label'] }}»">?</button>
                @endif
            </div>
        @endforeach
    </div>

    <p class="mt-2 text-xs text-ink-500">
        @if(!empty($health['date']))
            Данные на {{ \Illuminate\Support\Carbon::parse($health['date'])->format('d.m.Y') }}@if($forDemo), по правилам демо за 14 дней@else, считаются каждую ночь за последние 7 дней@endif.
        @else
            Расчёт ещё не запускался. Цвета появятся после ночного расчёта.
        @endif
    </p>
    @include($forDemo ? 'admin.health.help-demo' : 'admin.health.help')
</div>
