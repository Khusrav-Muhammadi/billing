@extends('layouts.app')

@section('title')
    {{ $requestTypeLabel ?? 'Продление' }}
@endsection

@section('content')
    <div class="mb-3">
        <h5 class="mb-1">{{ $requestTypeLabel ?? 'Продление' }}</h5>
    </div>
    @php
        $generatorFile = public_path('kp_generator_renewal/index.html');
        $query = [
            'csrf_token' => csrf_token(),
            'v' => @filemtime($generatorFile) ?: time(),
            'request_type' => $requestType ?? 'renewal',
        ];
        if (isset($offer) && $offer) {
            $query['offer_id'] = $offer->id;
        }
        $organizationId = request()->query('organization_id');
        if ($organizationId) {
            $query['organization_id'] = $organizationId;
        }
        $iframeUrl = asset('kp_generator_renewal/index.html') . '?' . http_build_query($query);
    @endphp
    <iframe
        src="{{ $iframeUrl }}"
        title="KP Generator Renewal"
        style="width: 100%; min-height: 90vh; border: 1px solid #e6e6e6; border-radius: 8px;"
    ></iframe>
@endsection
