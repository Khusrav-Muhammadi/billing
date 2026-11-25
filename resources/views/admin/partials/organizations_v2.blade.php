<table class="table table-hover">
    <thead>
    @php
        $sort = request('sort');
        $direction = strtolower((string) request('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortLink = function (string $key) use ($sort, $direction) {
            return request()->fullUrlWithQuery([
                'sort' => $key,
                'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc',
                'page' => null,
            ]);
        };
        $sortIcon = function (string $key) use ($sort, $direction) {
            if ($sort !== $key) {
                return '↕';
            }

            return $direction === 'asc' ? '↑' : '↓';
        };
    @endphp
    <tr>
        <th>#</th>
        @if(isset($healthByClient))
            <th>Здоровье</th>
        @endif
        <th><a href="{{ $sortLink('order_number') }}" class="text-dark text-decoration-none">ID {{ $sortIcon('order_number') }}</a></th>
        <th><a href="{{ $sortLink('name') }}" class="text-dark text-decoration-none">Имя {{ $sortIcon('name') }}</a></th>
        <th><a href="{{ $sortLink('phone') }}" class="text-dark text-decoration-none">Телефон {{ $sortIcon('phone') }}</a></th>
        <th><a href="{{ $sortLink('country') }}" class="text-dark text-decoration-none">Страна {{ $sortIcon('country') }}</a></th>
        <th><a href="{{ $sortLink('created_at') }}" class="text-dark text-decoration-none">Дата создания {{ $sortIcon('created_at') }}</a></th>
        <th><a href="{{ $sortLink('email') }}" class="text-dark text-decoration-none">Почта {{ $sortIcon('email') }}</a></th>
        <th><a href="{{ $sortLink('tariff') }}" class="text-dark text-decoration-none">Тариф {{ $sortIcon('tariff') }}</a></th>
        <th><a href="{{ $sortLink('users_count') }}" class="text-dark text-decoration-none">Кол-во пользователей {{ $sortIcon('users_count') }}</a></th>
        <th><a href="{{ $sortLink('balance') }}" class="text-dark text-decoration-none">Баланс {{ $sortIcon('balance') }}</a></th>
        <th><a href="{{ $sortLink('valid_until') }}" class="text-dark text-decoration-none">Срок действие {{ $sortIcon('valid_until') }}</a></th>
        <th><a href="{{ $sortLink('last_activity') }}" class="text-dark text-decoration-none">Последняя активность {{ $sortIcon('last_activity') }}</a></th>
        <th><a href="{{ $sortLink('status') }}" class="text-dark text-decoration-none">Статус {{ $sortIcon('status') }}</a></th>
        <th><a href="{{ $sortLink('partner') }}" class="text-dark text-decoration-none">Партнер {{ $sortIcon('partner') }}</a></th>
        <th><a href="{{ $sortLink('sub_domain') }}" class="text-dark text-decoration-none">Поддомен {{ $sortIcon('sub_domain') }}</a></th>
        <th>Действие</th>
    </tr>
    </thead>
    <tbody>
    @foreach($organizations as $organization)
        @php
            $isDemoRowActive = $organization->client?->is_demo
                && $organization->client?->created_at
                && $organization->client->created_at->greaterThan(now()->subDays(14));
        @endphp
        <tr class="organization-row"
            data-href="{{ route('organization_v2.show', $organization->id) }}"
            style="cursor: pointer;">
            <td>{{ method_exists($organizations, 'firstItem') ? (($organizations->firstItem() ?? 1) + $loop->index) : $loop->iteration }}</td>
            @if(isset($healthByClient))
                @php
                    $snapshot = $healthByClient[(int) $organization->client_id] ?? null;
                    $hDot = ['red' => '#ef4444', 'yellow' => '#f59e0b', 'grey' => '#9ca3af', 'green' => '#10b981'][$snapshot->color ?? ''] ?? null;
                @endphp
                <td class="text-nowrap" @if($snapshot) title="{{ $snapshot->reason_text }}" @endif>
                    @if($snapshot && $hDot)
                        <a href="{{ route('client-health.show', $organization->client_id) }}"
                           class="d-inline-flex align-items-center justify-content-center text-decoration-none"
                           style="width:40px;height:40px;border-radius:9999px;background:{{ $hDot }};color:#fff;font-weight:700;">{{ $snapshot->health_index }}</a>
                    @else
                        <span class="d-inline-flex align-items-center justify-content-center"
                              style="width:40px;height:40px;border-radius:9999px;border:2px dashed #e7e8eb;color:#cfd2da;">—</span>
                    @endif
                </td>
            @endif
            <td>{{ $organization->order_number ?? '-' }}</td>
            <td>{{ $organization->name }}</td>
            <td>{{ $organization->phone }}</td>
            <td>{{ $organization->client?->country?->name }}</td>
            <td>{{ optional($organization->created_at)->format('d.m.Y H:i') }}</td>
            <td>{{ $organization->client?->email }}</td>
            <td>{{ $organization->appTariff?->name ?? '-' }}</td>
            <td>{{ $organization->total_user_count }}</td>
            <td>
                {{ number_format((float) ($organization->real_balance ?? 0), 2, '.', ' ') }}
                {{ $organization->client?->country?->currency?->symbol_code ?? '' }}
            </td>
            <td>
                @if($organization->calculated_valid_until instanceof \Carbon\CarbonInterface)
                    {{ $organization->calculated_valid_until->format('d.m.Y') }}
                @elseif($organization->client?->validate_date instanceof \Carbon\CarbonInterface)
                    {{ $organization->client->validate_date->format('d.m.Y') }}
                @else
                    {{ $organization->client?->validate_date }}
                @endif
            </td>
            <td>{{ $organization->client?->last_activity }}</td>
            <td class="text-center">
                @if(($isDemoList ?? false) ? $isDemoRowActive : optional($organization->latestConnection)->status === 'connected')
                    <p style="color: #00bb00">Активный</p>
                @else
                    <p style="color: red">Неактивный</p>
                @endif
            </td>
            <td>{{ $organization->client?->partner?->name }}</td>
            <td>{{ $organization->client?->sub_domain }}</td>
            <td>
                <a href="{{ route('organization_v2.show', $organization->id) }}"><i class="mdi mdi-eye" style="font-size: 30px"></i></a>
                <a href="{{ route('organization.edit', $organization->id) }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                <a href="" data-bs-toggle="modal" data-bs-target="#deleteClient{{$organization->id}}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
            </td>
        </tr>
        <div class="modal fade" id="deleteClient{{$organization->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="{{ route('organization.destroy', $organization->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Удалить организацию</h5>
                        </div>
                        <div class="modal-body">
                            Вы уверены что хотите удалить эти данные?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-danger">Удалить</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
    </tbody>
</table>
@if(method_exists($organizations, 'links'))
    <div class="mt-3">
        {{ $organizations->links() }}
    </div>
@endif
