<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHAM CRM - Коммерческое предложение</title>
    <style>
        @page {
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            background: #f7f6f5;
            color: #1a1a2e;
            line-height: 1.6;
        }

        .page {
            width: 794px;
            min-height: 1123px;
            margin: 0 auto;
            background: #f7f6f5;
            position: relative;
            overflow: hidden;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* ===== PAGE 1 - COVER ===== */
        .cover-page {
            padding: 80px 60px 0;
            text-align: center;
        }

        .logo-container {
            margin-bottom: 60px;
        }

        .logo-container img {
            height: 200px;
            width: auto;
        }

        .main-title {
            font-size: 52px;
            font-weight: 900;
            text-align: center;
            line-height: 1.2;
            margin-bottom: 80px;
            color: #1a1a2e;
        }

        .info-section {
            width: 400px;
            margin: 0 auto;
            text-align: left;
        }

        .info-row {
            margin-bottom: 40px;
            display: table;
            width: 100%;
        }

        .info-icon {
            display: table-cell;
            width: 56px;
            height: 56px;
            background: rgba(69, 112, 255, 0.5);
            border-radius: 50%;
            text-align: center;
            vertical-align: middle;
            padding-right: 20px;
        }

        .info-icon img {
            width: 28px;
            height: 28px;
        }

        .info-content {
            display: table-cell;
            vertical-align: middle;
            padding-left: 20px;
        }

        .info-label {
            font-size: 14px;
            color: #6b7280;
            display: block;
        }

        .info-value {
            font-size: 18px;
            font-weight: 500;
            color: #1a1a2e;
        }

        .cover-decoration {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 350px;
            height: 150px;
        }

        /* ===== PAGE 2 - TARIFF ===== */
        .tariff-page {
            padding: 40px 50px;
            position: relative;
        }

        .header-decoration {
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 80px;
            background: linear-gradient(135deg, #2B4BFF 0%, #1a237e 100%);
            border-bottom-left-radius: 80px;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-header img {
            height: 60px;
            width: auto;
        }

        .tariff-header {
            margin-bottom: 25px;
        }

        .tariff-header-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .tariff-header-label {
            display: table-cell;
            width: 200px;
            font-size: 14px;
            color: #6b7280;
        }

        .tariff-header-value {
            display: table-cell;
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .tariff-table {
            width: 100%;
            border: 2px solid #2B4BFF;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
            border-collapse: collapse;
        }

        .tariff-table th {
            background: white;
            padding: 16px 20px;
            font-weight: 700;
            color: #2B4BFF;
            font-size: 14px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .tariff-table th:last-child {
            width: 60px;
            text-align: center;
        }

        .tariff-table td {
            background: white;
            padding: 14px 20px;
            font-size: 14px;
            color: #1a1a2e;
            border-bottom: 1px solid #e5e7eb;
        }

        .tariff-table tr:last-child td {
            border-bottom: none;
        }

        .tariff-table td:last-child {
            width: 60px;
            text-align: center;
        }

        .feature-title {
            font-weight: 500;
        }

        .feature-subtitle {
            color: #6b7280;
            font-size: 12px;
            margin-top: 2px;
        }

        .checkmark {
            color: #22c55e;
            font-size: 20px;
            font-weight: bold;
        }

        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }

        .summary-label {
            display: table-cell;
            width: 250px;
            font-size: 16px;
            font-weight: 500;
            color: #1a1a2e;
        }

        .summary-value {
            display: table-cell;
            font-size: 16px;
            font-weight: 700;
            color: #2B4BFF;
        }

        .bottom-decoration {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 250px;
            height: 100px;
        }

        /* ===== PAGE 3 - TOTAL COST ===== */
        .cost-page {
            padding: 40px 50px;
            position: relative;
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            color: #1a1a2e;
            margin-bottom: 30px;
            margin-top: 20px;
        }

        .cost-section {
            margin-bottom: 30px;
        }

        .cost-section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #2B4BFF;
        }

        .cost-table {
            width: 100%;
            border: 2px solid #2B4BFF;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .cost-table th {
            background: white;
            padding: 14px 20px;
            font-weight: 700;
            color: #2B4BFF;
            font-size: 14px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .cost-table th:last-child {
            width: 120px;
            text-align: right;
        }

        .cost-table td {
            background: white;
            padding: 14px 20px;
            font-size: 14px;
            color: #1a1a2e;
            border-bottom: 1px solid #e5e7eb;
        }

        .cost-table tr:last-child td {
            border-bottom: none;
        }

        .cost-table td:last-child {
            width: 120px;
            text-align: right;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            background: #e5e7eb;
            color: #6b7280;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 8px;
            vertical-align: middle;
        }

        /* Summary section */
        .totals-section {
            background: white;
            border: 2px solid #2B4BFF;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
        }

        .calculation-string {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .total-row {
            display: table;
            width: 100%;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .total-row:last-child {
            border-bottom: none;
        }

        .total-row.grand-total {
            border-top: 2px solid #2B4BFF;
            margin-top: 10px;
            padding-top: 15px;
        }

        .total-label {
            display: table-cell;
            font-size: 14px;
            color: #1a1a2e;
        }

        .total-value {
            display: table-cell;
            text-align: right;
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .total-row.grand-total .total-label,
        .total-row.grand-total .total-value {
            font-size: 18px;
            font-weight: 700;
            color: #2B4BFF;
        }

        .tagline {
            text-align: center;
            margin: 30px 0;
        }

        .tagline-text {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1.5;
        }

        .tagline-text span {
            color: #2B4BFF;
        }

        .contacts-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .contact-item {
            display: inline-block;
            margin: 0 25px;
            text-align: center;
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: rgba(69, 112, 255, 0.5);
            border-radius: 50%;
            margin: 0 auto 10px;
            line-height: 50px;
        }

        .contact-icon svg {
            width: 24px;
            height: 24px;
            vertical-align: middle;
        }

        .contact-value {
            font-size: 13px;
            color: #1a1a2e;
            font-weight: 500;
        }

        .validity-notice {
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .bottom-decoration-left {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 200px;
            height: 100px;
        }

        .bottom-decoration-right {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 200px;
            height: 100px;
        }

        .empty-state {
            color: #9ca3af;
            font-style: italic;
            padding: 10px 0;
        }
    </style>
</head>
<body>
<!-- PAGE 1 - COVER -->
<div class="page cover-page">
    <div class="logo-container">
        <img src="{{ asset('img/1.png') }}" alt="SHAM CRM">
    </div>

    <h1 class="main-title">КОММЕРЧЕСКОЕ<br>ПРЕДЛОЖЕНИЕ</h1>

    <div class="info-section">
        <div class="info-row">
            <div class="info-icon">
                <img src="{{ asset('img/638336.png') }}" alt="">
            </div>
            <div class="info-content">
                <span class="info-label">Клиент:</span>
                <span class="info-value">{{ $client_name }}</span>
            </div>
        </div>

        <div class="info-row">
            <div class="info-icon">
                <img src="{{ asset('img/677333 копия.png') }}" alt="">
            </div>
            <div class="info-content">
                <span class="info-label">Менеджер:</span>
                <span class="info-value">{{ $manager_name }}</span>
            </div>
        </div>

        <div class="info-row">
            <div class="info-icon">
                <img src="{{ asset('img/ФЫВ.png') }}" alt="">
            </div>
            <div class="info-content">
                <span class="info-label">Дата:</span>
                <span class="info-value">{{ $date }}</span>
            </div>
        </div>
    </div>

    <div class="cover-decoration">
        <svg viewBox="0 0 350 150" preserveAspectRatio="none" style="width: 100%; height: 100%;">
            <path fill="#1a237e" d="M350 150 L350 0 C300 20 250 60 180 80 C110 100 50 90 0 150 Z"/>
            <path fill="#2B4BFF" d="M350 150 L350 30 C310 50 270 80 200 95 C130 110 70 100 0 150 Z"/>
        </svg>
    </div>
</div>

<!-- PAGE 2 - TARIFF -->
<div class="page tariff-page">
    <div class="header-decoration"></div>

    <div class="page-header">
        <img src="{{ asset('img/logoWithText.png') }}" alt="SHAM CRM">
    </div>

    <div class="tariff-header">
        <div class="tariff-header-row">
            <span class="tariff-header-label">Тариф:</span>
            <span class="tariff-header-value">{{ $tariff['name'] }}</span>
        </div>
        <div class="tariff-header-row">
            <span class="tariff-header-label">Срок:</span>
            <span class="tariff-header-value">{{ $tariff['period'] }}</span>
        </div>
        <div class="tariff-header-row">
            <span class="tariff-header-label">Пользователей:</span>
            <span class="tariff-header-value">{{ $tariff['users_count'] }}</span>
        </div>
        <div class="tariff-header-row">
            <span class="tariff-header-label">Стоимость:</span>
            <span class="tariff-header-value">{{ $calculations['tariff_monthly_formatted'] }}/мес</span>
        </div>
    </div>

    <table class="tariff-table">
        <thead>
        <tr>
            <th>Входит в тариф</th>
            <th>✓</th>
        </tr>
        </thead>
        <tbody>
        @foreach($features as $feature)
            <tr>
                <td>
                    <div class="feature-title">{{ $feature['title'] }}</div>
                    @if(!empty($feature['subtitle']))
                        <div class="feature-subtitle">{{ $feature['subtitle'] }}</div>
                    @endif
                </td>
                <td>
                    @if($feature['included'])
                        <span class="checkmark">✓</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="bottom-decoration">
        <svg viewBox="0 0 250 100" preserveAspectRatio="none" style="width: 100%; height: 100%;">
            <path fill="#1a237e" d="M0 100 L0 0 C30 10 80 40 130 50 C180 60 220 50 250 100 Z"/>
            <path fill="#2B4BFF" d="M0 100 L0 20 C40 30 90 55 140 60 C190 65 230 55 250 100 Z"/>
        </svg>
    </div>
</div>

<!-- PAGE 3 - TOTAL COST -->
<div class="page cost-page">
    <div class="header-decoration"></div>

    <div class="page-header">
        <img src="{{ asset('img/logoWithText.png') }}" alt="SHAM CRM">
    </div>

    <h2 class="section-title">Итоговая стоимость</h2>

    <!-- Tariff -->
    <div class="cost-section">
        <div class="cost-section-title">Тариф</div>
        <table class="cost-table">
            <thead>
            <tr>
                <th>Наименование</th>
                <th>Сумма/мес</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <span class="badge">МЕС</span>
                    Тариф "{{ $tariff['name'] }}" ({{ $tariff['period'] }})
                </td>
                <td>{{ $calculations['tariff_monthly_formatted'] }}</td>
            </tr>
            </tbody>
        </table>
    </div>

    <!-- Additional Services -->
    @if(count($additional_services) > 0)
        <div class="cost-section">
            <div class="cost-section-title">Дополнительные услуги (ежемесячно)</div>
            <table class="cost-table">
                <thead>
                <tr>
                    <th>Наименование</th>
                    <th>Сумма/мес</th>
                </tr>
                </thead>
                <tbody>
                @foreach($additional_services as $service)
                    <tr>
                        <td>
                            <span class="badge">МЕС</span>
                            {{ $service['name'] }}
                            @if(isset($service['quantity']) && $service['quantity'] > 1)
                                (× {{ $service['quantity'] }})
                            @endif
                        </td>
                        <td>{{ $currency }}{{ number_format($service['monthly_price'] * ($service['quantity'] ?? 1), 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- One-time Payments -->
    @if(count($one_time_payments) > 0)
        <div class="cost-section">
            <div class="cost-section-title">Разовые оплаты</div>
            <table class="cost-table">
                <thead>
                <tr>
                    <th>Наименование</th>
                    <th>Сумма</th>
                </tr>
                </thead>
                <tbody>
                @foreach($one_time_payments as $payment)
                    <tr>
                        <td>{{ $payment['name'] }}</td>
                        <td>{{ $currency }}{{ number_format($payment['price'], 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Totals -->
    <div class="totals-section">
        <div class="calculation-string">
            {{ $calculations['summary_string'] }}
        </div>

        <div class="total-row">
            <span class="total-label">Ежемесячный платёж:</span>
            <span class="total-value">{{ $calculations['monthly_total_formatted'] }}</span>
        </div>

        <div class="total-row">
            <span class="total-label">За период ({{ $calculations['period_months'] }} мес):</span>
            <span class="total-value">{{ $calculations['period_total_formatted'] }}</span>
        </div>

        <div class="total-row">
            <span class="total-label">Единоразовые оплаты:</span>
            <span class="total-value">{{ $calculations['one_time_total_formatted'] }}</span>
        </div>

        <div class="total-row grand-total">
            <span class="total-label">ОБЩАЯ СУММА:</span>
            <span class="total-value">{{ $calculations['grand_total_formatted'] }}</span>
        </div>
    </div>

    <div class="tagline">
        <p class="tagline-text"><span>shamCRM</span> — CRM, которая<br>реально внедряется и работает</p>
    </div>

    <div class="contacts-section">
        <div class="contact-item">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2B4BFF" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
            </div>
            <span class="contact-value">{{ $contacts['phone'] }}</span>
        </div>

        <div class="contact-item">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2B4BFF" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="2" y1="12" x2="22" y2="12"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
            </div>
            <span class="contact-value">{{ $contacts['website'] }}</span>
        </div>

        <div class="contact-item">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="#2B4BFF">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                </svg>
            </div>
            <span class="contact-value">{{ $contacts['telegram'] }}</span>
        </div>
    </div>

    <p class="validity-notice">Предложение действительно {{ $validity_days }} дней</p>

    <div class="bottom-decoration-left">
        <svg viewBox="0 0 250 120" preserveAspectRatio="none" style="width: 100%; height: 100%;">
            <path fill="#1a237e" d="M0 120 L0 0 C40 20 100 60 160 70 C200 78 240 70 250 120 Z"/>
            <path fill="#2B4BFF" d="M0 120 L0 30 C50 45 110 75 170 82 C210 87 245 80 250 120 Z"/>
        </svg>
    </div>

    <div class="bottom-decoration-right">
        <svg viewBox="0 0 250 120" preserveAspectRatio="none" style="width: 100%; height: 100%;">
            <path fill="#1a237e" d="M250 120 L250 0 C210 20 150 60 90 70 C50 78 10 70 0 120 Z"/>
            <path fill="#2B4BFF" d="M250 120 L250 30 C200 45 140 75 80 82 C40 87 5 80 0 120 Z"/>
        </svg>
    </div>
</div>
</body>
</html>
