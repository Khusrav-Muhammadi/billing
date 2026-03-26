@extends('layouts.app')

@section('title')
    Коммерческое предложение
@endsection

@section('content')
            @php
                $iframeUrl = asset('kp/index.html') . '?' . http_build_query([
                    'manager' => auth()->user()?->name ?? '',
                    'author' => auth()->user()?->name ?? '',
                    'author_id' => auth()->id(),
                    'csrf_token' => csrf_token(),
                    'save_url' => route('application.kp.store'),
                    'create_client_url' => 'https://billing-back.shamcrm.com/api/sendRequest',
                    'v' => @filemtime(public_path('kp/index.html')) ?: time(),
                ]);
            @endphp
            <iframe
                src="{{ $iframeUrl }}"
                title="Генератор коммерческого предложения"
                style="width: 100%; min-height: 85vh; border: 1px solid #e6e6e6; border-radius: 8px;"
            ></iframe>
@endsection
