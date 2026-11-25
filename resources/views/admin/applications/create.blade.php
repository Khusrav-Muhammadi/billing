@extends('layouts.admin')

@section('title')
    {{ $requestTypeLabel ?? 'Подключение' }}
@endsection

@section('content')
    @php
        $query = [
            'csrf_token' => csrf_token(),
            'v' => @filemtime(public_path('kp_generator/index.html')) ?: time(),
            'request_type' => $requestType ?? 'connection',
        ];
        if (isset($offer) && $offer) {
            $query['offer_id'] = $offer->id;
        }
        $organizationId = request()->query('organization_id');
        if ($organizationId) {
            $query['organization_id'] = $organizationId;
        }
        $partnerId = request()->query('partner_id');
        if ($partnerId) {
            $query['partner_id'] = $partnerId;
        }
        $iframeUrl = asset('kp_generator/index.html') . '?' . http_build_query($query);
    @endphp
<div class="card overflow-hidden">
    <iframe
        src="{{ $iframeUrl }}"
        title="KP Generator"
        class="w-full border-0"
        style="height: calc(100vh - 4rem - 3rem);"
    ></iframe>
</div>
@endsection
