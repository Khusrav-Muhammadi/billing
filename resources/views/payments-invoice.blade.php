@extends('layouts.app')

@section('title') Счет на оплату @endsection

@section('content')
    @php
        $createdAt = $payment->created_at ? \Carbon\Carbon::parse($payment->created_at) : now();
        $invoiceDate = $createdAt->format('d.m.Y');
        $dueDate = $createdAt->copy()->addDays(7)->format('d.m.Y');
        $currencyLabel = 'сум';
        $totalSum = (float) $payment->sum;
        $totalFormatted = number_format($totalSum, 2, ',', ' ');
        $items = $payment->paymentItems ?? collect();
        $organizationOrderNumber = trim((string) ($organizationOrderNumber ?? ''));
    @endphp

    <div class="invoice-wrapper">
        <div class="invoice-actions">
            <a href="{{ route('client-payment.index') }}" class="btn btn-light">Назад</a>
            <button class="btn btn-primary" onclick="window.print()">Печать / Скачать PDF</button>
        </div>

        <div class="invoice-page">
            <h2 class="invoice-title">Счет на оплату № {{ $payment->id }} от {{ $invoiceDate }}</h2>

            <div class="invoice-block">
                <div><strong>Поставщик:</strong> "SOFTTECH GROUP" MCHJ ИНН: 311680486 Адрес: ГОРОД ТАШКЕНТ, ЯККАСАРАЙСКИЙ РАЙОН, Bog'saroy MFY, Mirobod ko'chasi, 10-uy</div>
            </div>

            <div class="invoice-block">
                <div><strong>Банковские реквизиты:</strong></div>
                <ul class="invoice-list">
                    <li>Банк получателя: КАПИТАЛБАНК "КАПИТАЛ 24" ЧАКАНА БИЗНЕС ФИЛИАЛИ</li>
                    <li>МФО: 01158</li>
                    <li>Расчетный счет: 2020800080715938001</li>
                </ul>
            </div>

            @if($offer->parner_id == 11)
                <div class="invoice-block invoice-divider">
                    <div>
                        <strong>Покупатель:</strong>
                        "{{ $payment->client_name }}"
                        @if($payment->client_name)
                            {{ $payment->client_name }}
                        @endif
                        @if($payment->client_name)
                            {{ $payment->client_name }}
                        @endif
                    </div>
                </div>
            @else
            <div class="invoice-block invoice-divider">
                <div>
                    <strong>Покупатель:</strong>
                    "{{ $payment->name }}"
                    @if($payment->phone)
                        {{ $payment->phone }}
                    @endif
                    @if($payment->email)
                        {{ $payment->email }}
                    @endif
                </div>
            </div>
            @endif
            <table class="invoice-table">
                <thead>
                <tr>
                    <th style="width: 50px;">№</th>
                    <th>Товары (работы, услуги)</th>
                    <th style="width: 80px;">Кол-во</th>
                    <th style="width: 120px;">Цена</th>
                    <th style="width: 120px;">Сумма</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $index => $item)
                    @php
                        $price = (float) ($item->price ?? 0);
                        $priceFormatted = number_format($price, 2, ',', ' ');
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->service_name }}</td>
                        <td>1</td>
                        <td>{{ $priceFormatted }}</td>
                        <td>{{ $priceFormatted }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="invoice-empty">Нет данных</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <div class="invoice-total">
             <!--   <div><strong>Итого:</strong> {{ $totalFormatted }} Без налога (НДС): —</div> -->
                <div><strong>Всего к оплате:</strong> {{ $totalFormatted }} ({{ $currencyLabel }})</div>
            </div>

            <div class="invoice-divider"></div>

            <div class="invoice-block">
                <div><strong>Условия:</strong></div>
                <ul class="invoice-list">
                    <li>Оплата данного счета означает согласие с условиями поставки услуги.</li>
                    <li>В назначении платежа обязательно указать ID организации: {{ $organizationOrderNumber !== '' ? $organizationOrderNumber : '—' }}.</li>
                </ul>
            </div>

            <div class="invoice-sign">
                <div>Руководитель: _____________ / Ахмедов М.Р.</div>
                <div>Бухгалтер: _____________</div>
            </div>
        </div>
    </div>

    <style>
        .invoice-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
        }
        .invoice-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 12px;
        }
        .invoice-page {
            background: #fff;
            padding: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .invoice-title {
            text-align: center;
            margin-bottom: 18px;
            font-size: 20px;
            font-weight: 700;
        }
        .invoice-block {
            font-size: 14px;
            margin-bottom: 12px;
            line-height: 1.45;
        }
        .invoice-list {
            margin: 6px 0 0 18px;
            padding: 0;
        }
        .invoice-divider {
            border-top: 1px solid #9ca3af;
            margin: 12px 0;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 8px;
        }
        .invoice-table th,
        .invoice-table td {
            border: 1px solid #111827;
            padding: 6px 8px;
            vertical-align: top;
        }
        .invoice-empty {
            text-align: center;
            color: #6b7280;
        }
        .invoice-total {
            margin-top: 12px;
            font-size: 14px;
            font-weight: 600;
        }
        .invoice-sign {
            margin-top: 18px;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        @media print {
            .btn, .nav, .sidebar, .navbar, .invoice-actions { display: none !important; }
            .invoice-wrapper { padding: 0; }
            .invoice-page { border: none; }
        }
    </style>
@endsection
