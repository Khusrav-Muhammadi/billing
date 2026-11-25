{{--
    Модалка «?» у плиток светофора. Тексты должны совпадать с расчётом в биллинге
    (billing: App\Services\ClientHealth\ClientHealthCalculator): поменяли правило там — поменяйте и здесь.
    Ожидает Alpine-переменную help ('red' | 'yellow' | 'grey' | 'green' | null) у родителя.
    x-teleport выносит окно в <body>, чтобы его не обрезали и не сдвигали родительские блоки.
--}}
@php
    $helpColors = [
        'red' => ['title' => 'Красный', 'subtitle' => 'Клиенту нужна помощь сейчас', 'dot' => '#ef4444', 'tone' => 'bg-red-50 text-red-800'],
        'yellow' => ['title' => 'Жёлтый', 'subtitle' => 'Активность клиента падает', 'dot' => '#f59e0b', 'tone' => 'bg-amber-50 text-amber-800'],
        'grey' => ['title' => 'Новый (серый)', 'subtitle' => 'Клиент только начал работать', 'dot' => '#9ca3af', 'tone' => 'bg-ink-100 text-ink-700'],
        'green' => ['title' => 'Зелёный', 'subtitle' => 'У клиента всё в порядке', 'dot' => '#10b981', 'tone' => 'bg-emerald-50 text-emerald-800'],
    ];
    $terms = [
        ['Индекс', 'Оценка клиента от 0 до 100. Складывается из четырёх баллов ниже, каждый умножается на свой вес. Главное — работа в CRM (диалоги и сущности, 75%), а не то, сколько сотрудников заходит.'],
        ['Входы · В · 15%', 'В сколько дней из 7 в CRM работал хоть кто-то из сотрудников клиента. 5 дней и больше — 100 баллов, 3 дня — 60.'],
        ['Охват · О · 10%', 'Какая доля пользователей зашла хотя бы раз за 7 дней. 30% и больше — 100 баллов: нормально, если из 10 сотрудников работают 3. Зашёл 1 из 10 — 33 балла.'],
        ['Диалоги · Д · 35%', 'Сколько чатов с клиентами (WhatsApp, Telegram, Instagram, сайт и другие каналы) получили за неделю ответ менеджера или ИИ. Сравнивается с обычной неделей клиента (медиана 4 прошлых недель): столько же или больше — 100 баллов, вдвое меньше — 50. Одна необычная неделя, например с импортом, базу не завышает. Автоответы бота-сценария и внутренние заметки не считаются.'],
        ['Сущности · С · 40%', 'Всё, что клиент создаёт и двигает в CRM за неделю: новые лиды, сделки, заказы и товары, плюс смены этапа у лидов, сделок и заказов. Сравнивается с обычной неделей клиента, как диалоги.'],
        ['Пункт «—»', 'Пункт не считается, если клиент им почти не пользуется: его не было в большинстве прошлых недель, или это меньше 10% всей работы клиента (диалоги + сущности). Его вес делится между остальными. Например, клиент, который работает с лидами без чатов, не теряет баллы за ноль диалогов.'],
        ['Пользователь', 'Сотрудник клиента с учётной записью в его CRM. Удалённые сотрудники не считаются.'],
        ['Вход', 'Любое действие пользователя в CRM за день, с веба или из мобильного приложения, а не только ввод пароля. Один пользователь за один день — один вход, сколько бы раз он ни открывал CRM. Ответы клиентам прямо из WhatsApp или Instagram на телефоне входом в CRM не считаются.'],
        ['Период', 'Последние 7 полных дней, по вчерашний день включительно. Всё пересчитывается каждую ночь, история хранится по дням.'],
        ['Оплачено до', 'До какого дня хватит баланса клиента по его текущему тарифу. У демо — день окончания 14-дневного демо.'],
    ];
@endphp
<template x-teleport="body">
    <div x-show="help" x-cloak x-transition.opacity
         class="fixed inset-0 z-[60] flex items-center justify-center bg-ink-900/50 p-3 sm:p-6"
         @click.self="help = null" @keydown.escape.window="help = null">
        <div class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true">

            {{-- Шапка --}}
            @foreach($helpColors as $key => $c)
                <div x-show="help === '{{ $key }}'" class="flex items-start gap-3 border-b border-ink-100 px-5 py-4 sm:px-6">
                    <span class="mt-1.5 shrink-0" style="display:inline-block;width:14px;height:14px;border-radius:9999px;background:{{ $c['dot'] }}"></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold leading-tight text-ink-900">{{ $c['title'] }}</h2>
                        <p class="text-sm text-ink-500">{{ $c['subtitle'] }}</p>
                    </div>
                    <button type="button" @click="help = null" class="-mr-1 shrink-0 rounded-lg p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-800" aria-label="Закрыть"><x-icon name="x" /></button>
                </div>
            @endforeach

            {{-- Содержимое прокручивается, шапка и кнопка остаются на месте --}}
            <div class="overflow-y-auto px-5 py-5 text-sm leading-relaxed text-ink-700 sm:px-6">
                @foreach($helpColors as $key => $c)
                    <div x-show="help === '{{ $key }}'" class="space-y-4">
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-500">Когда клиент попадает сюда</p>
                            @if($key === 'red')
                                <p class="mb-2">{!! 'Достаточно <b>одного</b> из условий:' !!}</p>
                                <ol class="space-y-2">
                                    @foreach([
                                        ['Индекс ниже 40.', null],
                                        ['За последние 7 дней в CRM не зашёл ни один пользователь.', 'Действует и для новых клиентов.'],
                                        ['Клиент был бы жёлтым, но до конца оплаты 30 дней или меньше.', 'Только для платных клиентов, у демо этого правила нет.'],
                                        ['CRM клиента недоступна.', 'В биллинге клиент подключён, но его CRM не открывается: удалён тенант, нет домена или CRM отвечает ошибкой. Индекс 0, в причине указан адрес CRM.'],
                                    ] as $i => [$rule, $note])
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-semibold text-red-700">{{ $i + 1 }}</span>
                                            <span><b class="text-ink-900">{{ $rule }}</b>@if($note) <span class="text-ink-500">{{ $note }}</span>@endif</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @elseif($key === 'yellow')
                                <p class="mb-2">Ни одно красное условие не выполнено, клиент не новый, и при этом:</p>
                                <ol class="space-y-2">
                                    @foreach([
                                        ['Индекс от 40 до 69,', null],
                                        ['или индекс 70 и выше, но за 2 недели упал на 20 баллов и больше.', 'Сравниваем с индексом этого же клиента 14 дней назад: было 95, стало 72 — жёлтый, хотя 72 само по себе зелёный индекс.'],
                                    ] as $i => [$rule, $note])
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-50 text-xs font-semibold text-amber-700">{{ $i + 1 }}</span>
                                            <span><b class="text-ink-900">{{ $rule }}</b>@if($note) <span class="text-ink-500">{{ $note }}</span>@endif</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @elseif($key === 'grey')
                                <p><b class="text-ink-900">Платный клиент в первые 28 дней после оплаты.</b> Отсчёт идёт от дня подключения. Если клиента отключали и он оплатил снова, 28 дней считаются заново.</p>
                                <p class="mt-2">В это время сотрудники только привыкают к CRM, поэтому низкий индекс и мало входов клиента не красят. Индекс всё равно считается и виден в списке.</p>
                            @else
                                <p class="mb-2">{!! 'Выполнены <b>все</b> условия:' !!}</p>
                                <ol class="space-y-2">
                                    @foreach([
                                        'Индекс 70 или выше.',
                                        'За 2 недели индекс не упал на 20 баллов и больше.',
                                        'За 7 дней заходил хотя бы один пользователь.',
                                        'CRM клиента открывается.',
                                    ] as $i => $rule)
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700">{{ $i + 1 }}</span>
                                            <b class="text-ink-900">{{ $rule }}</b>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>

                        @if($key === 'red')
                            <p class="rounded-lg border border-ink-100 px-3 py-2"><b class="text-ink-900">Исключение.</b> Новый платный клиент в первые 28 дней после оплаты по условиям 1 и 3 не краснеет, он серый. Красным он станет, только если за 7 дней никто не заходил или CRM недоступна.</p>
                        @elseif($key === 'yellow')
                            <p class="rounded-lg border border-ink-100 px-3 py-2"><b class="text-ink-900">Внимание.</b> Если у жёлтого платного клиента до конца оплаты 30 дней или меньше, он становится красным.</p>
                        @elseif($key === 'grey')
                            <p class="rounded-lg border border-ink-100 px-3 py-2"><b class="text-ink-900">Исключения.</b> Если за 7 дней не зашёл ни один пользователь или CRM недоступна, новый клиент сразу красный. Демо серыми не бывают: они ещё не платили, им сразу ставится обычный цвет.</p>
                        @endif

                        <div class="rounded-lg px-3 py-2 {{ $c['tone'] }}">
                            <b>Что делать: </b>
                            @if($key === 'red') связаться с клиентом в ближайшие дни, узнать, что мешает работать в CRM, и предложить помощь: обучение, настройку, интеграции.
                            @elseif($key === 'yellow') посмотреть, какой пункт проседает (чаще всего диалоги или сущности), и написать клиенту, пока он не стал красным.
                            @elseif($key === 'grey') помочь клиенту запуститься: обучить сотрудников, подключить каналы и настроить воронку.
                            @else ничего срочного. Хороший момент предложить продление, дополнительных пользователей или пакеты.
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="mt-6 border-t border-ink-100 pt-5">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-ink-500">Как считается индекс и что значат термины</p>
                    <dl class="divide-y divide-ink-100 rounded-xl border border-ink-100">
                        @foreach($terms as [$term, $text])
                            <div class="grid gap-1 px-4 py-3 sm:grid-cols-[150px_1fr] sm:gap-4">
                                <dt class="font-semibold text-ink-900">{{ $term }}</dt>
                                <dd class="text-ink-600">{{ $text }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>

            <div class="flex justify-end border-t border-ink-100 px-5 py-3 sm:px-6">
                <button type="button" @click="help = null" class="btn btn-primary px-5 py-2">Понятно</button>
            </div>
        </div>
    </div>
</template>
