@php
    $dots = ['red' => '#ef4444', 'yellow' => '#f59e0b', 'grey' => '#9ca3af', 'green' => '#10b981'];
    $labels = ['red' => 'Красный', 'yellow' => 'Жёлтый', 'grey' => 'Новый', 'green' => 'Зелёный'];
    $fmt = fn ($v) => $v === null ? '—' : (int) round($v);
@endphp
<div class="card mb-4 p-5">
    @if($snapshot)
        <div class="flex flex-wrap items-start gap-4">
            <a href="{{ route('client-health.show', $clientId) }}" title="{{ $snapshot->reason_text }}"
               class="inline-flex items-center justify-center rounded-full text-sm font-bold text-white"
               style="width:40px;height:40px;background:{{ $dots[$snapshot->color] ?? '#9ca3af' }}">{{ $snapshot->health_index }}</a>
            <div class="min-w-0 flex-1 text-sm text-ink-700">
                <p class="font-semibold text-ink-900">{{ $labels[$snapshot->color] ?? $snapshot->color }}</p>
                <p class="text-xs text-ink-500">Расчёт {{ $snapshot->date?->format('d.m.Y') }}</p>
                <p class="mt-1 text-xs text-ink-500">В {{ $fmt($snapshot->logins_score) }} · О {{ $fmt($snapshot->coverage_score) }} · Д {{ $fmt($snapshot->dialogs_score) }} · С {{ $fmt($snapshot->entities_score) }}</p>
                <p class="mt-2">{{ $snapshot->reason_text ?: '—' }}</p>
                <p class="mt-1 text-xs">Оплачено до:
                    @if($snapshot->paid_until)
                        {{ $snapshot->paid_until->format('d.m.Y') }}
                    @else
                        —
                    @endif
                </p>
                <a href="{{ route('client-health.show', $clientId) }}" class="mt-2 inline-block text-sm text-brand-600 hover:underline">История</a>
            </div>
        </div>
    @else
        <p class="text-sm text-ink-400">Индекс ещё не посчитан</p>
    @endif
</div>
