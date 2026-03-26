@extends('layouts.app')

@section('title')
    Просмотр КП
@endsection

@section('content')
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="card-title mb-0">Коммерческое предложение #{{ $offer->id }}</h4>
            <a href="{{ route('application.index') }}" class="btn btn-outline-secondary">Назад</a>
        </div>

        <div class="row mb-3">
            <div class="col-md-4"><strong>Клиент:</strong> {{ $offer->client_name }}</div>
            <div class="col-md-4"><strong>Email клиента:</strong> {{ $offer->client_email }}</div>
            <div class="col-md-4"><strong>Менеджер:</strong> {{ $offer->manager_name }}</div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4"><strong>Дата:</strong> {{ optional($offer->saved_at)->format('d.m.Y') }}</div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4"><strong>Тариф:</strong> {{ optional($offer->tariff)->tariff_name ?? '-' }}</div>
            <div class="col-md-4"><strong>Период:</strong> {{ $offer->period_months }} мес.</div>
            <div class="col-md-4"><strong>Валюта:</strong> {{ $offer->currency }}</div>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Услуга</th>
                    <th>Тип</th>
                    <th>Количество</th>
                    <th>Цена за ед.</th>
                    <th>Сумма</th>
                </tr>
                </thead>
                <tbody>
                @forelse($offer->services as $service)
                    <tr>
                        <td>{{ $service->service_name }}</td>
                        <td>{{ $service->billing_type }}</td>
                        <td>{{ $service->quantity }}</td>
                        <td>{{ number_format($service->unit_price, 2, '.', ' ') }}</td>
                        <td>{{ number_format($service->total_price, 2, '.', ' ') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Нет подключенных доп. услуг</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mb-2"><strong>Итог в месяц:</strong> {{ number_format($offer->monthly_total, 2, '.', ' ') }} {{ $offer->currency }}</div>
        <div class="mb-2"><strong>Итог за период:</strong> {{ number_format($offer->period_total, 2, '.', ' ') }} {{ $offer->currency }}</div>
        <div class="mb-4"><strong>Общая сумма:</strong> {{ number_format($offer->grand_total, 2, '.', ' ') }} {{ $offer->currency }}</div>

        @if($offer->pdf_path)
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($offer->pdf_path) }}"
               target="_blank"
               class="btn btn-primary">Открыть PDF</a>
        @endif
    </div>
@endsection

