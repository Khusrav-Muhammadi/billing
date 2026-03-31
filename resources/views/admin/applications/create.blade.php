@extends('layouts.app')

@section('title')
    {{ $requestTypeLabel ?? 'Подключение' }}
@endsection

@section('content')
    <div class="mb-3">
        <h5 class="mb-1">{{ $requestTypeLabel ?? 'Подключение' }}</h5>
    </div>
    @php
        $query = [
            'csrf_token' => csrf_token(),
            'v' => @filemtime(public_path('kp_generator/index.html')) ?: time(),
            'request_type' => $requestType ?? 'connection',
        ];
        if (isset($offer) && $offer) {
            $query['offer_id'] = $offer->id;
        }
        $iframeUrl = asset('kp_generator/index.html') . '?' . http_build_query($query);
    @endphp
    <iframe
        src="{{ $iframeUrl }}"
        title="KP Generator"
        style="width: 100%; min-height: 90vh; border: 1px solid #e6e6e6; border-radius: 8px;"
    ></iframe>
@endsection
