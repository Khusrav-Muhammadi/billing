@extends('layouts.app')

@section('title') Счет на оплату @endsection

@section('content')
    @php
        $paymentTypeLabel = [
            'invoice' => 'счет',
            'cash' => 'наличка',
            'alif' => 'карта (Alif)',
            'octo' => 'карта (Visa)',
        ][$payment->payment_type] ?? ($payment->payment_type ?? '-');
    @endphp
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="mb-0">Счет на оплату</h3>
                <div class="text-muted">Платеж #{{ $payment->id }}</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('client-payment.index') }}" class="btn btn-light">Назад</a>
                <button class="btn btn-primary" onclick="window.print()">Печать / Скачать PDF</button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Клиент</div>
                        <div class="fw-semibold">{{ $payment->name }}</div>
                        <div class="text-muted">{{ $payment->phone }}</div>
                        <div class="text-muted">{{ $payment->email }}</div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="text-muted small">К оплате</div>
                        <div class="fw-bold fs-3">{{ $payment->sum }}</div>
                        <div class="text-muted small">Тип: {{ $paymentTypeLabel }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Позиции</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Название</th>
                            <th style="width: 200px;">Цена</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($payment->paymentItems as $item)
                            <tr>
                                <td>{{ $item->service_name }}</td>
                                <td>{{ $item->price }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-muted">Нет данных</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Реквизиты для оплаты</div>
            <div class="card-body">
                <div class="text-muted">
                    Здесь можно разместить банковские реквизиты для оплаты по счету.
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .btn, .nav, .sidebar, .navbar { display: none !important; }
            .container { max-width: 100% !important; }
        }
    </style>
@endsection
