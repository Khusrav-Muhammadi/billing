@extends('layouts.app')

@section('title') ИИ-Подписки @endsection

@section('content')

    <form id="filterForm" method="GET" action="{{ route('ai-subscription.index') }}">
        <div class="row mb-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Статус</label>
                <select name="status" class="form-control">
                    <option value="">Статус</option>
                    <option value="1" @selected(request('status') === '1')>Активные</option>
                    <option value="0" @selected(request('status') === '0')>Неактивные</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Тариф</label>
                <select name="plan_id" class="form-control">
                    <option value="">Тариф</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((string)request('plan_id') === (string)$plan->id)>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Срок действия до</label>
                <input type="date" name="expires_before" class="form-control" value="{{ request('expires_before') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Поиск</label>
                <input type="text" name="search" class="form-control" placeholder="Организация" value="{{ request('search') }}">
            </div>
            <div class="col-md-2 mt-3">
                <button type="submit" class="btn btn-primary">Фильтр</button>
                <a href="{{ route('ai-subscription.index') }}" class="btn btn-outline-secondary">Сбросить</a>
            </div>
        </div>
    </form>

    <div class="card-body">
        <h4 class="card-title">ИИ-Подписки</h4>
        <div class="table-responsive">
            @php
                $sort      = request('sort');
                $direction = strtolower((string) request('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
                $sortLink  = function (string $key) use ($sort, $direction) {
                    return request()->fullUrlWithQuery([
                        'sort'      => $key,
                        'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc',
                        'page'      => null,
                    ]);
                };
                $sortIcon  = function (string $key) use ($sort, $direction) {
                    if ($sort !== $key) return '↕';
                    return $direction === 'asc' ? '↑' : '↓';
                };
            @endphp
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>#</th>
                    <th><a href="{{ $sortLink('id') }}" class="text-dark text-decoration-none">ID {{ $sortIcon('id') }}</a></th>
                    <th><a href="{{ $sortLink('organization') }}" class="text-dark text-decoration-none">Организация {{ $sortIcon('organization') }}</a></th>
                    <th>Телефон</th>
                    <th><a href="{{ $sortLink('plan') }}" class="text-dark text-decoration-none">Тариф {{ $sortIcon('plan') }}</a></th>
                    <th><a href="{{ $sortLink('period_months') }}" class="text-dark text-decoration-none">Период {{ $sortIcon('period_months') }}</a></th>
                    <th><a href="{{ $sortLink('status') }}" class="text-dark text-decoration-none">Статус {{ $sortIcon('status') }}</a></th>
                    <th><a href="{{ $sortLink('started_at') }}" class="text-dark text-decoration-none">Начало {{ $sortIcon('started_at') }}</a></th>
                    <th><a href="{{ $sortLink('expires_at') }}" class="text-dark text-decoration-none">Окончание {{ $sortIcon('expires_at') }}</a></th>
                    <th>Агент</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @forelse($subscriptions as $sub)
                    @php $balance = $sub->aiBalance ?? null; @endphp
                    <tr class="ai-subscription-row"
                        data-href="{{ route('ai-subscription.show', $sub->id) }}"
                        style="cursor: pointer;">
                        <td>{{ ($subscriptions->firstItem() ?? 1) + $loop->index }}</td>
                        <td>{{ $sub->id }}</td>
                        <td>{{ $sub->organization?->name ?? '—' }}</td>
                        <td>{{ $sub->organization?->phone ?? '—' }}</td>
                        <td>{{ $sub->plan?->name ?? '—' }}</td>
                        <td>{{ $sub->period_months }} мес.</td>
                        <td>
                            @if($sub->status)
                                <p style="color: #00bb00">Активна</p>
                            @else
                                <p style="color: red">Неактивна</p>
                            @endif
                        </td>
                        <td>{{ $sub->started_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            {{ $sub->expires_at?->format('d.m.Y') ?? '—' }}
                            @if($sub->expires_at && $sub->expires_at->isPast())
                                <span class="badge bg-danger ms-1">Истекла</span>
                            @elseif($sub->expires_at && $sub->expires_at->diffInDays(now()) <= 7)
                                <span class="badge bg-warning text-dark ms-1">Скоро</span>
                            @endif
                        </td>
                        <td>
                            @if($balance?->is_agent_enabled)
                                <p style="color: #00bb00">Включён</p>
                            @else
                                <p style="color: red">Выключен</p>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('ai-subscription.show', $sub->id) }}">
                                <i class="mdi mdi-eye" style="font-size: 30px"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">Подписок не найдено</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $subscriptions->links() }}</div>
    </div>

@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('click', event => {
                const row = event.target.closest('.ai-subscription-row');
                if (!row) return;
                if (event.target.closest('a, button, input, select, textarea, label, .modal')) return;
                const href = row.dataset.href;
                if (href) window.location.href = href;
            });
        });
    </script>
@endsection
