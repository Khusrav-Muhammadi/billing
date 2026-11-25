@php
    $dots = ['red' => '#ef4444', 'yellow' => '#f59e0b', 'grey' => '#9ca3af', 'green' => '#10b981'];
    $labels = ['red' => 'Красные', 'yellow' => 'Жёлтые', 'grey' => 'Новые', 'green' => 'Зелёные'];
@endphp
<div class="mb-4 rounded-xl border border-ink-200 bg-ink-50 p-4">
    <p class="mb-3 text-sm font-semibold text-ink-900">Здоровье клиентов</p>
    <div class="flex flex-wrap gap-2">
        @foreach($dots as $color => $dot)
            <a href="{{ route('organization_v2.index', ['partner' => $partner->id, 'health' => $color]) }}"
               class="inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm font-medium text-ink-800 no-underline hover:border-ink-300">
                <span style="display:inline-block;width:8px;height:8px;border-radius:9999px;background:{{ $dot }}"></span>
                {{ $labels[$color] }}
                <span class="font-semibold text-ink-900">{{ (int) ($partnerHealthCounts[$color] ?? 0) }}</span>
            </a>
        @endforeach
    </div>
    @if($partnerHealthRows->isEmpty())
        <p class="mb-0 mt-3 text-sm text-ink-500">Индекс ещё не посчитан</p>
    @else
        <div class="mt-3 overflow-x-auto">
            <table class="table mb-0">
                <thead>
                <tr>
                    <th>Клиент</th>
                    <th>Индекс</th>
                    <th>Причина</th>
                    <th>Оплачено до</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($partnerHealthRows as $row)
                    @php $orgId = $partnerHealthOrgs[$row->client_id] ?? null; @endphp
                    <tr>
                        <td>
                            @if($orgId)
                                <a href="{{ route('organization_v2.show', $orgId) }}">{{ $row->client?->name ?: ('#' . $row->client_id) }}</a>
                            @else
                                <a href="{{ route('sub_domain.show', $row->client_id) }}">{{ $row->client?->name ?: ('#' . $row->client_id) }}</a>
                            @endif
                        </td>
                        <td>
                            @include('admin.health.circle', ['snapshot' => $row, 'clientId' => $row->client_id])
                        </td>
                        <td class="small">{{ $row->reason_text ?: '—' }}</td>
                        <td class="text-nowrap">{{ $row->paid_until?->format('d.m.Y') ?: '—' }}</td>
                        <td><a href="{{ route('client-health.show', $row->client_id) }}">История</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
