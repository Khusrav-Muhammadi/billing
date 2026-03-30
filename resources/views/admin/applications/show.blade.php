@extends('layouts.app')

@section('title')
    Просмотр КП
@endsection

@section('content')
    <div class="card-body">
        @php
            $query = [
                'csrf_token' => csrf_token(),
                'v' => @filemtime(public_path('kp_generator/index.html')) ?: time(),
                'offer_id' => $offer->id,
            ];
            if ($offer->locked_at) {
                $query['locked'] = 1;
            }
            $iframeUrl = asset('kp_generator/index.html') . '?' . http_build_query($query);
        @endphp

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="card-title mb-0">Коммерческое предложение #{{ $offer->id }}</h4>
            <a href="{{ route('application.index') }}" class="btn btn-outline-secondary">Назад</a>
        </div>

        @if($offer->locked_at)
            <div class="alert alert-success mb-3">
                Ссылка на оплату уже была сгенерирована {{ optional($offer->locked_at)->format('d.m.Y H:i') }}.
                Редактирование КП отключено.
            </div>
        @endif

        <iframe
            src="{{ $iframeUrl }}"
            title="Просмотр КП"
            style="width: 100%; min-height: 90vh; border: 1px solid #e6e6e6; border-radius: 8px;"
        ></iframe>
    </div>
@endsection
