@extends('layouts.admin')

@section('title')
    {{ $requestTypeLabel ?? 'Подключение доп услуг' }}
@endsection

@section('content')
    @php
        $generatorFile = public_path('kp_generator_extra/index.html');
        $query = [
            'csrf_token' => csrf_token(),
            'v' => @filemtime($generatorFile) ?: time(),
            'request_type' => $requestType ?? 'connection_extra_services',
        ];
        if (isset($offer) && $offer) {
            $query['offer_id'] = $offer->id;
        }
        $organizationId = request()->query('organization_id');
        if ($organizationId) {
            $query['organization_id'] = $organizationId;
        }
        $iframeUrl = asset('kp_generator_extra/index.html') . '?' . http_build_query($query);
    @endphp
<div class="card overflow-hidden">
    <iframe
        src="{{ $iframeUrl }}"
        title="KP Generator Extra Services"
        class="w-full border-0"
        style="height: calc(100vh - 4rem - 3rem);"
    ></iframe>
</div>
@endsection
