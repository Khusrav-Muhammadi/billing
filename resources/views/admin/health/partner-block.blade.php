@php
    $dots = ['red' => '#ef4444', 'yellow' => '#f59e0b', 'grey' => '#9ca3af', 'green' => '#10b981'];
    $labels = ['red' => 'Красные', 'yellow' => 'Жёлтые', 'grey' => 'Новые', 'green' => 'Зелёные'];
    $pills = ['red' => 'badge-danger', 'yellow' => 'badge-warning', 'grey' => 'badge-secondary', 'green' => 'badge-success'];
@endphp
<div class="card mb-4 p-3">
    <h4 class="card-title">Здоровье клиентов партнёра</h4>
    <div class="row mb-3">
        @foreach($dots as $color => $dot)
            <div class="col-md-3 mb-2">
                <a href="{{ route('organization_v2.index', ['partner' => $partner->id, 'health' => $color]) }}" class="d-block text-decoration-none">
                    <span class="badge {{ $pills[$color] }}">● {{ $labels[$color] }} {{ (int) ($partnerHealthCounts[$color] ?? 0) }}</span>
                </a>
            </div>
        @endforeach
    </div>
    @if($partnerHealthRows->isEmpty())
        <p class="text-muted mb-0">Индекс ещё не посчитан</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm">
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
                            <a href="{{ route('client-health.show', $row->client_id) }}" title="{{ $row->reason_text }}"
                               class="d-inline-flex align-items-center justify-content-center text-decoration-none"
                               style="width:40px;height:40px;border-radius:9999px;background:{{ $dots[$row->color] ?? '#9ca3af' }};color:#fff;font-weight:700;">{{ $row->health_index }}</a>
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
