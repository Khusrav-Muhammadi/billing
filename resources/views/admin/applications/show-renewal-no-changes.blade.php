@extends('layouts.admin')

@section('title')
    Продление #{{ $offer->id }}
@endsection

@section('content')
    <div>
        @php
            $isPaidOffer = (string) ($offer->latestOfferStatus?->status ?? '') === 'paid'
                || (string) ($offer->status ?? '') === 'paid';
            $requestTypeLabel = 'Продление';
            $generatorPath = 'kp_generator_renewal_no_changes/index.html';
            $generatorFile = public_path($generatorPath);
            $query = [
                'csrf_token' => csrf_token(),
                'v' => @filemtime($generatorFile) ?: time(),
                'offer_id' => $offer->id,
                'is_paid' => $isPaidOffer ? 1 : 0,
                'request_type' => 'renewal_no_changes',
            ];
            if ($offer->locked_at) {
                $query['locked'] = 1;
            }
            $iframeUrl = asset($generatorPath) . '?' . http_build_query($query);
        @endphp

        <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <a href="{{ route('application.index') }}" class="btn btn-outline-secondary">Назад</a>
            <span class="badge badge-secondary">{{ $requestTypeLabel }} #{{ $offer->id }}</span>
        </div>

        <div class="card overflow-hidden">
            <iframe
                src="{{ $iframeUrl }}"
                title="Просмотр: {{ $requestTypeLabel }}"
                class="w-full border-0"
                style="height: calc(100vh - 9rem);"
            ></iframe>
        </div>
    </div>
@endsection
