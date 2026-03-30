@extends('layouts.app')

@section('title')
    Коммерческое предложение
@endsection

@section('content')
    @php
        $query = [
            'csrf_token' => csrf_token(),
            'v' => @filemtime(public_path('kp_generator/index.html')) ?: time(),
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
