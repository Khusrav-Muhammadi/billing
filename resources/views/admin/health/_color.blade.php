@php
    $healthColors = [
        'red' => ['label' => 'Красный', 'badge' => 'bg-red-50 text-red-700', 'dot' => '#ef4444'],
        'yellow' => ['label' => 'Жёлтый', 'badge' => 'bg-amber-50 text-amber-700', 'dot' => '#f59e0b'],
        'grey' => ['label' => 'Новый', 'badge' => 'bg-ink-100 text-ink-600', 'dot' => '#9ca3af'],
        'green' => ['label' => 'Зелёный', 'badge' => 'bg-emerald-50 text-emerald-700', 'dot' => '#10b981'],
    ];
    $c = $healthColors[$color ?? ''] ?? ['label' => $color ?? '—', 'badge' => 'bg-ink-100 text-ink-600', 'dot' => '#9ca3af'];
@endphp
<span class="badge {{ $c['badge'] }}"><span style="display:inline-block;width:8px;height:8px;border-radius:9999px;background:{{ $c['dot'] }}"></span>{{ $c['label'] }}</span>
