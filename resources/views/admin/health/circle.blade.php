@php
    $dots = ['red' => '#ef4444', 'yellow' => '#f59e0b', 'grey' => '#9ca3af', 'green' => '#10b981'];
    $dot = $dots[$snapshot->color ?? ''] ?? null;
@endphp
@if($snapshot && $dot && !empty($clientId))
    <a href="{{ route('client-health.show', $clientId) }}"
       title="{{ $snapshot->reason_text }}"
       onclick="event.stopPropagation()"
       class="text-decoration-none"
       style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:9999px;background:{{ $dot }};color:#fff;font-weight:700;">{{ $snapshot->health_index }}</a>
@else
    <span title="Индекс ещё не посчитан"
          style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:9999px;border:2px dashed #e7e8eb;color:#cfd2da;">—</span>
@endif
