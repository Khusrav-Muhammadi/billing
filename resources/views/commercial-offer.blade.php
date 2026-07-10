@php
    function formatPrice($number) {
        return number_format((float) $number, 2, ',', ' ');
    }

    if (!function_exists('getAiPromoOriginalPrice')) {
        function getAiPromoOriginalPrice($serviceName, $currency) {
            $name = mb_strtolower(trim($serviceName));
            $name = str_replace('ё', 'е', $name);
            $curr = mb_strtolower(trim($currency));
            
            $cleanName = preg_replace('/^внедрение\s*:\s*/u', '', $name);
            
            $isAiPromo = str_contains($cleanName, 'deepsales') || 
                         (str_contains($cleanName, 'asterix') && str_contains($cleanName, 'скидка'));
                         
            if (!$isAiPromo) {
                return null;
            }
            
            $isImpl = str_starts_with($name, 'внедрение:');
            
            if (str_contains($cleanName, 'deepsales')) {
                if ($isImpl) {
                    if ($curr === '$' || str_contains($curr, 'usd') || str_contains($curr, 'dollar') || str_contains($curr, 'доллар')) {
                        return 500;
                    } elseif (str_contains($curr, 'сомони') || str_contains($curr, 'tjs')) {
                        return 5000;
                    } else {
                        return 6000000;
                    }
                } else {
                    if (str_contains($cleanName, 'mini')) {
                        if ($curr === '$' || str_contains($curr, 'usd') || str_contains($curr, 'dollar') || str_contains($curr, 'доллар')) {
                            return 38;
                        } elseif (str_contains($curr, 'сомони') || str_contains($curr, 'tjs')) {
                            return 375;
                        } else {
                            return 450000;
                        }
                    } elseif (str_contains($cleanName, 'start')) {
                        if ($curr === '$' || str_contains($curr, 'usd') || str_contains($curr, 'dollar') || str_contains($curr, 'доллар')) {
                            return 60;
                        } elseif (str_contains($curr, 'сомони') || str_contains($curr, 'tjs')) {
                            return 600;
                        } else {
                            return 720000;
                        }
                    } else { // pro
                        if ($curr === '$' || str_contains($curr, 'usd') || str_contains($curr, 'dollar') || str_contains($curr, 'доллар')) {
                            return 90;
                        } elseif (str_contains($curr, 'сомони') || str_contains($curr, 'tjs')) {
                            return 900;
                        } else {
                            return 1150000;
                        }
                    }
                }
            }
            
            if (str_contains($cleanName, 'asterix')) {
                if (str_contains($cleanName, 'до 15')) {
                    if ($isImpl) {
                        if ($curr === '$' || str_contains($curr, 'usd') || str_contains($curr, 'dollar') || str_contains($curr, 'доллар')) {
                            return 400;
                        } elseif (str_contains($curr, 'сомони') || str_contains($curr, 'tjs')) {
                            return 4000;
                        } else {
                            return 5000000;
                        }
                    } else {
                        if ($curr === '$' || str_contains($curr, 'usd') || str_contains($curr, 'dollar') || str_contains($curr, 'доллар')) {
                            return 50;
                        } elseif (str_contains($curr, 'сомони') || str_contains($curr, 'tjs')) {
                            return 500;
                        } else {
                            return 500000;
                        }
                    }
                } else { // больше 15
                    if ($isImpl) {
                        if ($curr === '$' || str_contains($curr, 'usd') || str_contains($curr, 'dollar') || str_contains($curr, 'доллар')) {
                            return 800;
                        } elseif (str_contains($curr, 'сомони') || str_contains($curr, 'tjs')) {
                            return 8000;
                        } else {
                            return 10000000;
                        }
                    } else {
                        if ($curr === '$' || str_contains($curr, 'usd') || str_contains($curr, 'dollar') || str_contains($curr, 'доллар')) {
                            return 100;
                        } elseif (str_contains($curr, 'сомони') || str_contains($curr, 'tjs')) {
                            return 1000;
                        } else {
                            return 1000000;
                        }
                    }
                }
            }
            
            return null;
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

        @page {
            margin-top: 40px;
        }

        @page :first {
            margin-top: 0;
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
            padding: 40px 60px;
            background: #ffffff;
        }

        /* Avoid breaking inside sections - keeps tariff/modules/services together */
        .section-block {
            page-break-inside: avoid;
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

        .price-breakdown {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
            line-height: 1.2;
        }

        .price-before-discount {
            color: #8b95aa;
            font-size: 13px;
            font-weight: 500;
            text-decoration: line-through;
        }

        .discount-line {
            color: #16a34a;
            font-size: 12px;
            font-weight: 700;
        }

        .price-after-discount {
            color: var(--brand-blue);
            font-size: 16px;
            font-weight: 800;
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

<!-- ========== CONTENT PAGES ========== -->
<div class="page-content">
    <div class="page-header">
        <img class="page-logo" src="https://billing-back.shamcrm.com/img/logoWithText.png" alt="SHAM CRM">
        <div class="page-link">*подробнее: <a href="https://shamcrm.com/price" target="_blank">shamcrm.com</a></div>
    </div>

    <!-- TARIFF SECTION -->
    <div class="section-block">
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

        @php
            $is12Months = ((int)$tariff['period_months'] === 12);
            if ($is12Months) {
                $originalPrice = round((float)$tariff['monthly_price'] / 0.85, 2);
                $discountAmount = $originalPrice - (float)$tariff['monthly_price'];
            }
        @endphp
        <div class="tariff-price-label" style="text-align: center; margin-bottom: 30px;">
            <div>Стоимость тарифа:</div>
            @if($is12Months)
                <div class="price-breakdown" style="display: inline-flex; flex-direction: column; align-items: center; gap: 5px; line-height: 1.2; margin-top: 10px;">
                    <span class="price-before-discount" style="font-size: 18px; font-weight: 500; text-decoration: line-through; color: #8b95aa;">{{ formatPrice($originalPrice) }} {{ $currency }}</span>
                    <span class="discount-line" style="font-size: 16px; font-weight: 700; color: #16a34a;">Скидка 15% (−{{ formatPrice($discountAmount) }} {{ $currency }})</span>
                    <div class="tariff-price-value" style="font-size: 24px; font-weight: 800; color: var(--brand-blue);">{{ formatPrice($tariff['monthly_price']) }} {{ $currency }}/мес.</div>
                </div>
            @else
                <div class="tariff-price-value" style="font-size: 24px; font-weight: 800; color: var(--brand-blue); margin-top: 10px;">{{ formatPrice($tariff['monthly_price']) }} {{ $currency }}/мес.</div>
            @endif
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

    <!-- ADDITIONAL USERS SECTION -->
    @if(isset($additional_users) && $additional_users['quantity'] > 0)
    <div class="section-block" style="margin-top: 40px;">
        <h2 class="section-title">Дополнительные пользователи</h2>
        <table class="users-table">
            <tr>
                <td>Доп. пользователь</td>
                <td>{{ $additional_users['quantity'] }} шт.</td>
                <td>{{ formatPrice($additional_users['price_per_user']) }} {{ $currency }}/мес.</td>
            </tr>
        </table>
    </div>
    @endif

    <!-- MODULES SECTION -->
    <div class="section-block" style="margin-top: 40px;">
        <h2 class="section-title">Дополнительные модули</h2>
        <table class="modules-table">
            @foreach($modules as $module)
            <tr>
                <td>{{ $module['name'] }}</td>
                <td class="{{ $module['status'] === 'included' ? 'check' : ($module['status'] === 'not_available' ? 'cross' : '') }}">
                    @if($module['status'] === 'included')
                        ✓
                    @elseif($module['status'] === 'selected')
                        @php
                            $aiPromoOriginalPrice = getAiPromoOriginalPrice($module['name'], $currency);
                        @endphp
                        @if($aiPromoOriginalPrice !== null)
                            <div class="price-breakdown">
                                <div class="price-before-discount">{{ formatPrice($aiPromoOriginalPrice) }} {{ $currency }}</div>
                                <div class="discount-line">
                                    Скидка 100%
                                    (−{{ formatPrice($aiPromoOriginalPrice) }} {{ $currency }})
                                </div>
                                <div class="price-after-discount">{{ formatPrice(0) }} {{ $currency }}</div>
                            </div>
                        @else
                            {{ formatPrice($module['price']) }} {{ $currency }}
                        @endif
                    @else
                        ✗
                    @endif
                </td>
            </tr>
            @endforeach
        </table>
    </div>

    <!-- ONE-TIME SERVICES SECTION -->
    @if(isset($one_time_services) && count($one_time_services) > 0)
    @php
        $implementationData = is_array($implementation ?? null) ? $implementation : [];
        $implementationBasePrice = (float)($implementationData['base_price'] ?? 0);
        $implementationFinalPrice = (float)($implementationData['price'] ?? 0);
        $implementationDiscountPercent = (float)($implementationData['discount_percent'] ?? 0);
        $implementationBaseDiscountPercent = (float)($implementationData['discount_percent_base'] ?? 0);
        $implementationMonths12DiscountPercent = (float)($implementationData['discount_percent_12_extra'] ?? 0);
        $implementationDiscountAmount = (float)($implementationData['discount_amount'] ?? max(0, $implementationBasePrice - $implementationFinalPrice));
        $hasImplementationDiscount = $implementationBasePrice > 0
            && $implementationFinalPrice >= 0
            && $implementationDiscountAmount > 0.01
            && $implementationDiscountPercent > 0;
    @endphp
    <div class="section-block" style="margin-top: 40px;">
        <h2 class="section-title">Услуги внедрения и обучения (разово)</h2>
        <table class="services-table">
            @foreach($one_time_services as $service)
            @php
                $isImplementationService = trim((string)($service['name'] ?? '')) === 'Внедрение';
                $showImplementationDiscount = $isImplementationService
                    && $hasImplementationDiscount
                    && isset($service['status'])
                    && $service['status'] === 'selected';
                
                $aiPromoOriginalPrice = getAiPromoOriginalPrice($service['name'] ?? '', $currency);
            @endphp
            <tr>
                <td>{{ $service['name'] }}</td>
                <td class="{{ (isset($service['status']) && $service['status'] === 'included') ? 'check' : '' }}">
                    @if(isset($service['value']))
                        {{ $service['value'] }}
                    @elseif(isset($service['status']) && $service['status'] === 'included')
                        ✓
                    @elseif($showImplementationDiscount)
                        <div class="price-breakdown">
                            <div class="price-before-discount">{{ formatPrice($implementationBasePrice) }} {{ $currency }}</div>
                            <div class="discount-line">
                                Скидка {{ rtrim(rtrim(number_format($implementationDiscountPercent, 2, ',', ' '), '0'), ',') }}%
                                (−{{ formatPrice($implementationDiscountAmount) }} {{ $currency }})
                            </div>
                        
                            <div class="price-after-discount">{{ formatPrice($service['price']) }} {{ $currency }}</div>
                        </div>
                    @elseif($aiPromoOriginalPrice !== null)
                        <div class="price-breakdown">
                            <div class="price-before-discount">{{ formatPrice($aiPromoOriginalPrice) }} {{ $currency }}</div>
                            <div class="discount-line">
                                Скидка 100%
                                (−{{ formatPrice($aiPromoOriginalPrice) }} {{ $currency }})
                            </div>
                            <div class="price-after-discount">{{ formatPrice(0) }} {{ $currency }}</div>
                        </div>
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

    <!-- TOTAL SECTION -->
    <div class="section-block" style="margin-top: 40px;">
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
                <div class="contact-value">{{$contacts['phone']}}</div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">
                    <img src="https://billing-back.shamcrm.com/img/world.png" alt="Web">
                </div>
                <div class="contact-value"><a href="https://shamcrm.com/price">shamcrm.com/price</a></div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">
                    <img src="https://billing-back.shamcrm.com/img/telegram.png" alt="Telegram">
                </div>
                <div class="contact-value">
                    @php
                        $telegram = $contacts['telegram'] ?? '@shamcrm_uz';
                        $username = ltrim($telegram, '@');
                    @endphp

                    <a href="https://t.me/{{ $username }}" target="_blank" rel="noopener">
                        {{ $telegram }}
                    </a>
                </div>
            </div>
        </div>

        <div class="validity-line">
            Предложение действительно до: <span> {{now()->addDays(14)->format('d.m.yy')}}</span>
        </div>

        <div class="warning-box" style="margin-top: 30px; padding: 20px; border: 1px solid #fecaca; background-color: #fff5f5; border-radius: 12px; text-align: left; color: #b91c1c; font-size: 15px; line-height: 1.5; font-family: 'Cygrotesk', 'Inter', Arial, sans-serif;">
            <div style="font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">⚠️</span> ВАЖНОЕ УВЕДОМЛЕНИЕ:
            </div>
            <div>
                Оплата принимается только в безналичной форме на расчетный счет компании или через официальных партнеров. Передача наличных денежных средств сотрудникам компании или на их карту <strong>ЗАПРЕЩЕНА</strong>. В случае нарушения данного правила компания не несет ответственность.
            </div>
        </div>
    </div>
</div>

</body>
</html>
