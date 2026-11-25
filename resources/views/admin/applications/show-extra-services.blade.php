@extends('layouts.admin')

@section('title')
    Просмотр КП
@endsection

@section('content')
    <div class="card-body">
        @php
            $isPaidOffer = (string) ($offer->latestOfferStatus?->status ?? '') === 'paid'
                || (string) ($offer->status ?? '') === 'paid';
            $requestTypeLabel = 'Подключение доп услуг';
            $generatorPath = 'kp_generator_extra/index.html';
            $generatorFile = public_path($generatorPath);
            $query = [
                'csrf_token' => csrf_token(),
                'v' => @filemtime($generatorFile) ?: time(),
                'offer_id' => $offer->id,
                'is_paid' => $isPaidOffer ? 1 : 0,
                'request_type' => 'connection_extra_services',
            ];
            if ($offer->locked_at) {
                $query['locked'] = 1;
            }
            $iframeUrl = asset($generatorPath) . '?' . http_build_query($query);
        @endphp

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="card-title mb-0">{{ $requestTypeLabel }} #{{ $offer->id }}</h4>
            <a href="{{ route('application.index') }}" class="btn btn-outline-secondary">Назад</a>
        </div>

<div class="card overflow-hidden">
    <iframe
        src="{{ $iframeUrl }}"
        title="Просмотр: {{ $requestTypeLabel }}"
        class="w-full border-0"
        style="height: calc(100vh - 4rem - 3rem);"
    ></iframe>
</div>
    </div>
@endsection
