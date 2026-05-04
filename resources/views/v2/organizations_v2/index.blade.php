@extends('layouts.app')

@section('title')
    Организация
@endsection


@section('content')

    <form id="filterForm" method="GET" action="{{ route('organization_v2.index') }}">
        <div class="row mb-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Тип подключения</label>
                <select name="clientType" class="form-control">
                    <option value="">Тип подключения</option>
                    <option value="Клиенты" @selected(request('clientType') === 'Клиенты')>Клиенты</option>
                    <option value="Партнеры" @selected(request('clientType') === 'Партнеры')>Партнеры</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Статус</label>
                <select name="status" class="form-control">
                    <option value="">Статус</option>
                    <option value="1" @selected(request('status') === '1')>Активный</option>
                    <option value="0" @selected(request('status') === '0')>Неактивный</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Тариф</label>
                <select name="tariff" class="form-control">
                    <option value="">Тариф</option>
                    @foreach($tariffs as $tariff)
                        <option value="{{ $tariff->id }}" @selected((string)request('tariff') === (string)$tariff->id)>{{ $tariff->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Страна</label>
                <select name="country" class="form-control">
                    <option value="">Страна</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" @selected((string)request('country') === (string)$country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Партнер</label>
                <select name="partner" class="form-control">
                    <option value="">Партнер</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}" @selected((string)request('partner') === (string)$partner->id)>{{ $partner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Поиск</label>
                <input type="text" name="search" class="form-control" placeholder="Поиск" value="{{ request('search') }}">
            </div>
            <div class="col-md-2 mt-3">
                <label class="form-label">Срок действия до</label>
                <input type="date" name="valid_until_to" class="form-control" value="{{ request('valid_until_to') }}">
            </div>
            <div class="col-md-12 mt-3">
                <button type="submit" class="btn btn-primary">Фильтр</button>
                <a href="{{ route('organization_v2.index') }}" class="btn btn-outline-secondary">Сбросить</a>
            </div>
        </div>
    </form>
	    <div class="card-body">
	        <h4 class="card-title">Клиенты</h4>
	        <a href="{{ route('client.create') }}" type="button" class="btn btn-primary">Создать</a>
	        <div class="table-responsive">
	            @include('admin.partials.organizations_v2', ['organizations' => $organizations])
	        </div>
	    </div>

@endsection


@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('click', event => {
                const row = event.target.closest('.organization-row');
                if (!row) {
                    return;
                }

                if (event.target.closest('a, button, input, select, textarea, label, .modal')) {
                    return;
                }

                const href = row.dataset.href;
                if (href) {
                    window.location.href = href;
                }
            });
        });


    </script>
@endsection
