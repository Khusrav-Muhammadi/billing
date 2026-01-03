@php
    // Форматирование числа с сохранением десятичных (если есть)
    function formatPrice($number) {
        if (floor($number) == $number) {
            // Целое число
            return number_format($number, 0, ',', ' ');
        } else {
            // Дробное число - показываем 2 знака после запятой
            return number_format($number, 2, ',', ' ');
        }
    }
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Коммерческое предложение | SHAM CRM</title>
    <style>
        :root {
            --brand-blue: #2f4df6;
            --text-primary: #0b1b35;
            --text-secondary: #1f2c45;
        }

        @font-face {
            font-family: 'Cygrotesk';
            src: url('https://billing-back.shamcrm.com/img/CYGROTESK-KEYMEDIUM.OTF') format('opentype');
            font-weight: 500;
            font-style: normal;
        }

        @font-face {
            font-family: 'Cygrotesk';
            src: url('https://billing-back.shamcrm.com/img/CYGROTESK-KEYBOLD.OTF') format('opentype');
            font-weight: 700;
            font-style: normal;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cygrotesk', 'Inter', Arial, sans-serif;
            background: #ffffff;
            color: var(--text-primary);
            margin: 0;
            padding: 0;
        }

        /* ========== PAGE 1 - WELCOME ========== */
        .page-welcome {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 50px 40px 40px;
            background: #f4f6fb;
            overflow: hidden;
            page-break-after: always;
        }

        .page-welcome::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url("https://billing-back.shamcrm.com/img/main_backgoun.png");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.18;
            z-index: 0;
            pointer-events: none;
        }

        .page-welcome > * {
            position: relative;
            z-index: 1;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-mark {
            width: 260px;
            height: 260px;
            object-fit: contain;
            margin: 0 auto 20px;
            display: block;
        }

        .hero {
            position: relative;
            width: 100%;
            max-width: 600px;
            border-radius: 64px;
            padding: 20px 40px;
            text-align: center;
            margin-bottom: 40px;
            background: linear-gradient(90deg,
            rgba(47, 77, 246, 0.92) 0%,
            rgba(47, 77, 246, 0.8) 45%,
            rgba(47, 77, 246, 0.38) 100%);
            box-shadow: 0 8px 32px rgba(47, 77, 246, 0.25);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .hero-title {
            margin: 0;
            font-size: 55px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            width: 100%;
            max-width: 700px;
            padding: 0;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 16px;
            background: rgba(47, 77, 246, 0.6);
            box-shadow: 0 4px 16px rgba(47, 77, 246, 0.2);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .icon img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: brightness(0) invert(1);
            position: relative;
            z-index: 1;
        }

        .icon svg {
            width: 36px;
            height: 36px;
            fill: #ffffff;
            position: relative;
            z-index: 1;
        }

        .label {
            font-size: 25px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .value {
            font-size: 20px;
            font-weight: 500;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .laptop-image {
            position: absolute;
            bottom: -30px;
            right: -180px;
            width: 800px;
            height: 400px;
            z-index: 2;
            pointer-events: none;
        }

        /* ========== COMMON PAGE STYLES ========== */
        .page-content {
            position: relative;
            width: 100%;
            min-height: 100vh;
            padding: 40px 60px;
            background: #ffffff;
            page-break-after: always;
        }

        .page-content:last-child {
            page-break-after: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e8ecf3;
        }

        .page-logo {
            height: 50px;
            width: auto;
        }

        .page-link {
            font-size: 14px;
            color: #6b7a99;
        }

        .page-link a {
            color: var(--brand-blue);
            text-decoration: underline;
            font-weight: 600;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 24px;
            text-align: center;
        }

        /* ========== PAGE 2 - TARIFF ========== */
        .tariff-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .tariff-info {
            text-align: left;
        }

        .tariff-info-right {
            text-align: right;
        }

        .tariff-label {
            font-size: 16px;
            color: #6b7a99;
            font-weight: 500;
        }

        .tariff-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .tariff-price-label {
            font-size: 18px;
            font-weight: 700;
            color: var(--brand-blue);
            margin-bottom: 10px;
        }

        .tariff-price-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--brand-blue);
        }

        .features-table {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            border-collapse: collapse;
        }

        .features-table td {
            padding: 14px 0;
            border-bottom: 1px solid #e8ecf3;
            font-size: 16px;
            color: var(--text-primary);
        }

        .features-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--brand-blue);
        }

        .features-table .check {
            color: #22c55e;
            font-size: 20px;
        }

        /* ========== PAGE 3 - MODULES & USERS ========== */
        .modules-table {
            width: 100%;
            max-width: 700px;
            margin: 0 auto 40px;
            border-collapse: collapse;
        }

        .modules-table td {
            padding: 14px 0;
            border-bottom: 1px solid #e8ecf3;
            font-size: 16px;
            color: var(--text-primary);
        }

        .modules-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--brand-blue);
        }

        .modules-table .check {
            color: #22c55e;
            font-size: 20px;
        }

        .modules-table .cross {
            color: #ef4444;
            font-size: 20px;
        }

        .users-table {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            border-collapse: collapse;
        }

        .users-table td {
            padding: 14px 0;
            border-bottom: 1px solid #e8ecf3;
            font-size: 16px;
            color: var(--text-primary);
        }

        .users-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--brand-blue);
        }

        /* ========== PAGE 4 - ONE-TIME SERVICES ========== */
        .services-table {
            width: 100%;
            max-width: 700px;
            margin: 0 auto 40px;
            border-collapse: collapse;
        }

        .services-table td {
            padding: 14px 0;
            border-bottom: 1px solid #e8ecf3;
            font-size: 16px;
            color: var(--text-primary);
        }

        .services-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--brand-blue);
        }

        .services-table .check {
            color: #22c55e;
            font-size: 20px;
        }

        .services-total {
            max-width: 700px;
            margin: 40px auto 0;
            padding-top: 20px;
            border-top: 2px solid #e8ecf3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .services-total-label {
            font-size: 20px;
            font-weight: 700;
            color: var(--brand-blue);
        }

        .services-total-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--brand-blue);
            text-decoration: underline;
        }

        /* ========== PAGE 5 - TOTAL ========== */
        .total-section {
            max-width: 600px;
            margin: 0 auto 40px;
            padding: 30px;
            border: 1px solid #e8ecf3;
            border-radius: 16px;
        }

        .total-table {
            width: 100%;
            border-collapse: collapse;
        }

        .total-table td {
            padding: 14px 0;
            border-bottom: 1px solid #e8ecf3;
            font-size: 16px;
            color: var(--text-primary);
        }

        .total-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--text-primary);
        }

        .total-table tr:last-child td {
            border-bottom: none;
        }

        .total-row {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e8ecf3;
            text-align: right;
            font-size: 22px;
            color: var(--brand-blue);
            font-weight: 700;
        }

        .total-row span {
            color: var(--text-primary);
        }

        .slogan-box {
            max-width: 500px;
            margin: 0 auto 40px;
            padding: 40px 30px;
            border: 1px solid #e8ecf3;
            border-radius: 16px;
            text-align: center;
        }

        .slogan-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .slogan-text strong {
            color: var(--brand-blue);
        }

        .contacts-row {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-bottom: 40px;
        }

        .contact-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--brand-blue);
            border-radius: 50%;
            margin-bottom: 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .contact-icon svg {
            width: 28px;
            height: 28px;
            fill: #ffffff;
        }

        .contact-icon img {
            width: 28px;
            height: 28px;
            filter: brightness(0) invert(1);
        }

        .contact-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .validity-line {
            text-align: center;
            font-size: 18px;
            color: var(--text-primary);
            padding-top: 30px;
            border-top: 1px solid #e8ecf3;
        }

        .validity-line span {
            display: inline-block;
            min-width: 150px;
            border-bottom: 2px solid var(--text-primary);
            margin-left: 10px;
        }

        /* Print/PDF styles */
        @media print {
            body {
                background: #ffffff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page-welcome {
                padding: 30px;
                min-height: auto;
            }

            .hero {
                background: var(--brand-blue) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .icon {
                background: var(--brand-blue) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .contact-icon {
                background: var(--brand-blue) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- ========== PAGE 1 - WELCOME ========== -->
<div class="page-welcome">
    <div class="header">
        <img class="logo-mark" src="https://billing-back.shamcrm.com/img/logo.png" alt="SHAM CRM logo">
    </div>

    <div class="hero">
        <h1 class="hero-title">КОММЕРЧЕСКОЕ<br>ПРЕДЛОЖЕНИЕ</h1>
    </div>

    <div class="meta">
        <div class="meta-item">
            <div class="icon">
                <img src="https://billing-back.shamcrm.com/img/clients.png" alt="Клиент">
            </div>
            <div class="label">Клиент:</div>
            <p class="value">{{ $client }}</p>
        </div>

        <div class="meta-item">
            <div class="icon">
                <img src="https://billing-back.shamcrm.com/img/manager.png" alt="Менеджер">
            </div>
            <div class="label">Менеджер:</div>
            <p class="value">{{ $manager }}</p>
        </div>

        <div class="meta-item">
            <div class="icon">
                <img src="https://billing-back.shamcrm.com/img/calendar.png" alt="Дата">
            </div>
            <div class="label">Дата:</div>
            <p class="value">{{ $date }}</p>
        </div>
    </div>

    <img class="laptop-image" src="https://billing-back.shamcrm.com/img/laptop.png" alt="Laptop">
</div>

<!-- ========== PAGE 2 - TARIFF ========== -->
<div class="page-content">
    <div class="page-header">
        <img class="page-logo" src="https://billing-back.shamcrm.com/img/logoWithText.png" alt="SHAM CRM">
        <div class="page-link">*подробнее: <a href="https://shamcrm.com" target="_blank">shamcrm.com</a></div>
    </div>

    <div class="tariff-header">
        <div class="tariff-info">
            <div class="tariff-label">Тариф:</div>
            <div class="tariff-value">{{ $tariff['name'] }}</div>
        </div>
        <div class="tariff-info-right">
            <div class="tariff-label">Период подписки:</div>
            <div class="tariff-value">{{ $tariff['period_months'] }} месяцев</div>
        </div>
    </div>

    <div class="tariff-price-label" style="text-align: center; margin-bottom: 30px;">
        Стоимость тарифа: <span class="tariff-price-value">{{ formatPrice($tariff['monthly_price']) }} {{ $currency }}/мес.</span>
    </div>

    @if(isset($tariff_features) && count($tariff_features) > 0)
    <table class="features-table">
        @foreach($tariff_features as $feature)
        <tr>
            <td>{{ $feature['name'] }}</td>
            <td class="{{ isset($feature['value']) ? '' : 'check' }}">
                @if(isset($feature['value']))
                    {{ $feature['value'] }}
                @else
                    ✓
                @endif
            </td>
        </tr>
        @endforeach
    </table>
    @endif
</div>

<!-- ========== PAGE 3 - MODULES & USERS ========== -->
<div class="page-content">
    <div class="page-header">
        <img class="page-logo" src="https://billing-back.shamcrm.com/img/logoWithText.png" alt="SHAM CRM">
        <div class="page-link">*подробнее: <a href="https://shamcrm.com" target="_blank">shamcrm.com</a></div>
    </div>

    @if(isset($additional_users) && $additional_users['quantity'] > 0)
    <h2 class="section-title">Дополнительные пользователи</h2>
    <table class="users-table">
        <tr>
            <td>Доп. пользователь</td>
            <td>{{ $additional_users['quantity'] }} шт.</td>
            <td>{{ formatPrice($additional_users['price_per_user']) }} {{ $currency }}/мес.</td>
        </tr>
    </table>
    @endif

    <h2 class="section-title" style="margin-top: 40px;">Дополнительные модули</h2>
    <table class="modules-table">
        @foreach($modules as $module)
        <tr>
            <td>{{ $module['name'] }}</td>
            <td class="{{ $module['status'] === 'included' ? 'check' : ($module['status'] === 'not_available' ? 'cross' : '') }}">
                @if($module['status'] === 'included')
                    ✓
                @elseif($module['status'] === 'selected')
                    {{ formatPrice($module['price']) }} {{ $currency }}
                @else
                    ✗
                @endif
            </td>
        </tr>
        @endforeach
    </table>
</div>

<!-- ========== PAGE 4 - ONE-TIME SERVICES ========== -->
@if(isset($one_time_services) && count($one_time_services) > 0)
<div class="page-content">
    <div class="page-header">
        <img class="page-logo" src="https://billing-back.shamcrm.com/img/logoWithText.png" alt="SHAM CRM">
        <div class="page-link">*подробнее: <a href="https://shamcrm.com" target="_blank">shamcrm.com</a></div>
    </div>

    <h2 class="section-title">Услуги внедрения и обучения (разово)</h2>
    <table class="services-table">
        @foreach($one_time_services as $service)
        <tr>
            <td>{{ $service['name'] }}</td>
            <td class="{{ (isset($service['status']) && $service['status'] === 'included') ? 'check' : '' }}">
                @if(isset($service['value']))
                    {{ $service['value'] }}
                @elseif(isset($service['status']) && $service['status'] === 'included')
                    ✓
                @elseif(isset($service['status']) && $service['status'] === 'selected' && isset($service['price']))
                    {{ formatPrice($service['price']) }} {{ $currency }}
                @else
                    ✓
                @endif
            </td>
        </tr>
        @endforeach
    </table>

    <div class="services-total">
        <div class="services-total-label">Стоимость<br>подключение и внедрения:</div>
        <div class="services-total-value">{{ formatPrice($calculations['one_time_total']) }} {{ $currency }}</div>
    </div>
</div>
@endif

<!-- ========== PAGE 5 - TOTAL ========== -->
<div class="page-content">
    <div class="page-header">
        <img class="page-logo" src="https://billing-back.shamcrm.com/img/logoWithText.png" alt="SHAM CRM">
        <div class="page-link">*подробнее: <a href="https://shamcrm.com" target="_blank">shamcrm.com</a></div>
    </div>

    <h2 class="section-title">Итоговая стоимость</h2>

    <div class="total-section">
        <table class="total-table">
            <tr>
                <td>Тариф "{{ $tariff['name'] }}" за {{ $tariff['period_months'] }} мес.</td>
                <td>{{ formatPrice($calculations['tariff_total']) }} {{ $currency }}</td>
            </tr>
            @if($calculations['modules_total'] > 0)
            <tr>
                <td>Дополнительные модули за {{ $tariff['period_months'] }} мес.</td>
                <td>{{ formatPrice($calculations['modules_total']) }} {{ $currency }}</td>
            </tr>
            @endif
            @if(isset($additional_users) && $additional_users['quantity'] > 0)
            <tr>
                <td>Доп. пользователи ({{ $additional_users['quantity'] }} шт.) за {{ $tariff['period_months'] }} мес.</td>
                <td>{{ formatPrice($calculations['users_total']) }} {{ $currency }}</td>
            </tr>
            @endif
            @if($calculations['one_time_total'] > 0)
            <tr>
                <td>Подключение и внедрение</td>
                <td>{{ formatPrice($calculations['one_time_total']) }} {{ $currency }}</td>
            </tr>
            @endif
        </table>
        <div class="total-row">
            <span>Итог:</span> {{ formatPrice($calculations['grand_total']) }} {{ $currency }}
        </div>
    </div>

    <div class="slogan-box">
        <p class="slogan-text">
            <strong>shamCRM</strong> — CRM, которая<br>
            реально внедряется и работает!
        </p>
    </div>

    <div class="contacts-row">
        <div class="contact-item">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                </svg>
            </div>
            <div class="contact-value">{{ $contacts['phone'] ?? '+998 78 555 7416' }}</div>
        </div>

        <div class="contact-item">
            <div class="contact-icon">
                <img src="https://billing-back.shamcrm.com/img/world.png" alt="Web">
            </div>
            <div class="contact-value">{{ $contacts['website'] ?? 'shamcrm.com' }}</div>
        </div>

        <div class="contact-item">
            <div class="contact-icon">
                <img src="https://billing-back.shamcrm.com/img/telegram.png" alt="Telegram">
            </div>
            <div class="contact-value">{{ $contacts['telegram'] ?? '@shamcrm_uz' }}</div>
        </div>
    </div>

    <div class="validity-line">
        Предложение действительно до: <span>{{ $validity_date ?? '' }}</span>
    </div>
</div>

</body>
</html>
