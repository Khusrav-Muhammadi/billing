@php
    $labels = ['red' => 'Красный', 'yellow' => 'Жёлтый', 'grey' => 'Новый', 'green' => 'Зелёный'];
    $fmt = fn ($v) => $v === null ? '—' : (int) round($v);
@endphp
<div class="card mb-4 p-5">
    <p class="mb-3 text-sm font-semibold text-ink-900">Здоровье</p>
    <div class="flex flex-wrap items-start gap-4">
        @include('admin.health.circle', ['snapshot' => $snapshot ?? null, 'clientId' => $clientId ?? null])
        @if($snapshot)
            <div class="min-w-0 flex-1 text-sm text-ink-700">
                <p class="font-semibold text-ink-900">{{ $labels[$snapshot->color] ?? $snapshot->color }}</p>
                <p class="text-xs text-ink-500">Расчёт {{ $snapshot->date?->format('d.m.Y') }}</p>
                <p class="mt-1 text-xs text-ink-500">В {{ $fmt($snapshot->logins_score) }} · О {{ $fmt($snapshot->coverage_score) }} · Д {{ $fmt($snapshot->dialogs_score) }} · С {{ $fmt($snapshot->entities_score) }}</p>
                <p class="mt-2">{{ $snapshot->reason_text ?: '—' }}</p>
                <p class="mt-1 text-xs">Оплачено до:
                    {{ $snapshot->paid_until ? $snapshot->paid_until->format('d.m.Y') : '—' }}
                </p>
                @if(!empty($clientId))
                    <a href="{{ route('client-health.show', $clientId) }}" class="mt-2 inline-block text-sm text-brand-600 hover:underline">История</a>
                @endif
            </div>
        @else
            <p class="mb-0 text-sm text-ink-500">Индекс ещё не посчитан. Считается каждую ночь за последние 7 дней.</p>
        @endif
    </div>
</div>
