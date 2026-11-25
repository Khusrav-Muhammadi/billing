{{--
    «?» у плиток светофора на странице демо. Правила — ClientHealthCalculator::demo().
    Ожидает Alpine-переменную help ('red' | 'yellow' | 'grey' | 'green' | null).
--}}
@php
    $helpColors = [
        'red' => ['title' => 'Красный', 'subtitle' => 'Демо не запустилось', 'dot' => '#ef4444', 'tone' => 'bg-red-50 text-red-800', 'do' => 'написать и помочь зайти в CRM: с 3-го дня никто не входил, либо демо почти кончилось, а работы так и нет.'],
        'yellow' => ['title' => 'Жёлтый', 'subtitle' => 'Зашли, но ничего не сделали', 'dot' => '#f59e0b', 'tone' => 'bg-amber-50 text-amber-800', 'do' => 'показать, как завести лид, сделку или диалог, пока 14 дней не кончились.'],
        'grey' => ['title' => 'Серый', 'subtitle' => 'Демо только создали', 'dot' => '#9ca3af', 'tone' => 'bg-ink-100 text-ink-700', 'do' => 'подождать до 3-го дня. В первые два дня цвет не ставим.'],
        'green' => ['title' => 'Зелёный', 'subtitle' => 'Демо пользуются', 'dot' => '#10b981', 'tone' => 'bg-emerald-50 text-emerald-800', 'do' => 'хороший момент предложить оплату.'],
    ];
@endphp
<template x-teleport="body">
    <div x-show="help" x-cloak x-transition.opacity
         class="fixed inset-0 z-[60] flex items-center justify-center bg-ink-900/50 p-3 sm:p-6"
         @click.self="help = null" @keydown.escape.window="help = null">
        <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true">
            @foreach($helpColors as $key => $c)
                <div x-show="help === '{{ $key }}'" class="flex items-start gap-3 border-b border-ink-100 px-5 py-4">
                    <span class="mt-1.5 shrink-0" style="display:inline-block;width:14px;height:14px;border-radius:9999px;background:{{ $c['dot'] }}"></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold leading-tight text-ink-900">{{ $c['title'] }}</h2>
                        <p class="text-sm text-ink-500">{{ $c['subtitle'] }}</p>
                    </div>
                    <button type="button" @click="help = null" class="-mr-1 shrink-0 rounded-lg p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-800" aria-label="Закрыть"><x-icon name="x" /></button>
                </div>
            @endforeach
            <div class="overflow-y-auto px-5 py-5 text-sm leading-relaxed text-ink-700">
                <p>Демо бесплатное и живёт 14 дней, поэтому здесь не индекс платных клиентов. В кружке 0, 40 или 80.</p>
                <ul class="mt-3 list-disc space-y-1 pl-5">
                    <li><b class="text-ink-900">Серый</b> — 1-й и 2-й день после создания.</li>
                    <li><b class="text-ink-900">Зелёный, 80</b> — кто-то входил, и за 14 дней есть лид, сделка, заказ, товар, смена этапа или диалог.</li>
                    <li><b class="text-ink-900">Жёлтый, 40</b> — входили, но такой работы нет.</li>
                    <li><b class="text-ink-900">Красный, 0</b> — с 3-го дня никто ни разу не входил, или осталось 2 дня и меньше, а работы всё ещё нет.</li>
                </ul>
                @foreach($helpColors as $key => $c)
                    <p x-show="help === '{{ $key }}'" class="mt-4 rounded-lg px-3 py-2 {{ $c['tone'] }}"><b>Что делать: </b>{{ $c['do'] }}</p>
                @endforeach
            </div>
            <div class="flex justify-end border-t border-ink-100 px-5 py-3">
                <button type="button" @click="help = null" class="btn btn-primary px-5 py-2">Понятно</button>
            </div>
        </div>
    </div>
</template>
