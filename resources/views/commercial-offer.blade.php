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

        /* PDF-ready page container */
        .page {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 50px 40px 40px;
            background: #f4f6fb;
            overflow: hidden;
        }

        .page::before {
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

        /* Контент поверх фонового псевдо-элемента */
        .page > * {
            position: relative;
            z-index: 1;
        }
        /* Header section - теперь отдельно от карточки */
        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-mark {
            width: 400px;
            height: 400px;
            object-fit: contain;
            margin: 0 auto 20px;
            display: block;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .brand .sham {
            color: var(--text-primary);
        }

        .brand .crm {
            color: var(--brand-blue);
            border: 3px solid var(--brand-blue);
            border-radius: 10px;
            padding: 6px 16px 4px;
            line-height: 1;
        }


        /* Meta cards section */
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
        .hero {
            position: relative;
            width: 100%;
            max-width: 600px;
            border-radius: 64px;
            padding: 36px 40px;
            text-align: center;
            margin-bottom: 70px;
            /* Прямо на элементе */
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

        .icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 16px;
            /* Прямо на элементе */
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

        /* SVG icons as fallback */
        .icon svg {
            width: 36px;
            height: 36px;
            fill: #ffffff;
            position: relative;
            z-index: 1;
        }

        .label {
            font-size:25px;
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

        /* Footer */
        .footer-note {
            text-align: center;
            margin-top: 40px;
            color: #8892a6;
            font-size: 13px;
        }

        /* Print/PDF styles */
        @media print {
            body {
                background: #ffffff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page {
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
        }

        /* ========== PAGE 2 STYLES ========== */
        .page-2 {
            position: relative;
            width: 100%;
            min-height: 100vh;
            padding: 40px 60px;
            background: #ffffff;
        }

        .page-2-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 50px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e8ecf3;
        }

        .page-2-logo {
            height: 50px;
            width: auto;
        }

        .page-2-link {
            font-size: 14px;
            color: #6b7a99;
        }

        .page-2-link a {
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

        .price-table {
            width: 100%;
            max-width: 700px;
            margin: 0 auto 50px;
            border-collapse: collapse;
        }

        .price-table th,
        .price-table td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #e8ecf3;
            font-size: 16px;
        }

        .price-table th {
            font-weight: 500;
            color: #6b7a99;
        }

        .price-table td {
            color: var(--text-primary);
        }

        .price-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--brand-blue);
        }

        .price-table .check {
            color: #22c55e;
            font-size: 20px;
        }

        .modules-table {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
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

        /* ========== PAGE 3 STYLES ========== */
        .page-3 {
            position: relative;
            width: 100%;
            min-height: 100vh;
            padding: 40px 60px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
        }

        .page-3-content {
            flex: 1;
        }

        .total-section {
            max-width: 600px;
            margin: 0 auto 60px;
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
            margin: 0 auto 60px;
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
            margin-bottom: 50px;
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

        /* Responsive */
        @media (max-width: 700px) {
            .page {
                padding: 30px 20px;
            }

            .logo-mark {
                width: 100px;
                height: 100px;
            }

            .brand {
                font-size: 28px;
            }

            .hero {
                padding: 28px 24px;
                border-radius: 18px;
            }

            .hero-title {
                font-size: 24px;
            }

            .meta {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .page-2 {
                padding: 30px 20px;
            }

            .page-2-header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .section-title {
                font-size: 20px;
            }

            .price-table th,
            .price-table td,
            .modules-table td {
                font-size: 14px;
                padding: 12px 8px;
            }

            .page-3 {
                padding: 30px 20px;
            }

            .total-section {
                padding: 20px;
            }

            .slogan-box {
                padding: 30px 20px;
            }

            .slogan-text {
                font-size: 18px;
            }

            .contacts-row {
                flex-direction: column;
                gap: 30px;
            }

            .contact-icon {
                width: 50px;
                height: 50px;
            }

            .validity-line {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <img class="logo-mark" src="https://billing-back.shamcrm.com/img/logo.png" alt="SHAM CRM logo">

    </div>

    <div class="hero">
        <h1 class="hero-title">КОММЕРЧЕСКОЕ<br>ПРЕДЛОЖЕНИЕ</h1>
    </div>

    <div class="meta">
        <div class="meta-item">
            <div class="icon">
                <img src="https://billing-back.shamcrm.com/img/clients.png" alt="Клиент"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <svg style="display: none;" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                </svg>
            </div>
            <div class="label">Клиент:</div>
            <p class="value">{{ $client }}</p>
        </div>

        <div class="meta-item">
            <div class="icon">
                <img src="https://billing-back.shamcrm.com/img/manager.png" alt="Менеджер"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <svg style="display: none;" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>
            <div class="label">Менеджер:</div>
            <p class="value">{{ $manager }}</p>
        </div>

        <div class="meta-item">
            <div class="icon">
                <img src="https://billing-back.shamcrm.com/img/calendar.png" alt="Дата"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <svg style="display: none;" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>
                </svg>
            </div>
            <div class="label">Дата:</div>
            <p class="value">{{ $date }}</p>
        </div>
    </div>

</div>

<!-- ========== PAGE 2 ========== -->
<div class="page-2">
    <div class="page-2-header">
        <img class="page-2-logo" src="https://billing-back.shamcrm.com/img/logoWithText.png" alt="SHAM CRM">
        <div class="page-2-link">*подробнее: <a href="https://shamcrm.com" target="_blank">shamcrm.com</a></div>
    </div>

    <h2 class="section-title">Дополнительные пользователи</h2>
    <table class="price-table">
        <tr>
            <td>Доп. пользователь</td>
            <td>1 шт.</td>
            <td>25 800 сум/мес.</td>
        </tr>
    </table>

    <h2 class="section-title">Дополнительные модули</h2>
    <table class="modules-table">
        <tr>
            <td>Mini-app B2B (партнёры, дилеры)</td>
            <td class="check">✓</td>
        </tr>
        <tr>
            <td>Mini-app B2C (клиенты, заявки)</td>
            <td class="check">✓</td>
        </tr>
        <tr>
            <td>IP-Телефония (Сипуну)</td>
            <td class="check">✓</td>
        </tr>
        <tr>
            <td>Подключение IP-телефонии</td>
            <td class="check">✓</td>
        </tr>
        <tr>
            <td>Интернет-магазин</td>
            <td>399 000 сум</td>
        </tr>
        <tr>
            <td>Подключения интернет-магазин</td>
            <td>*** сум</td>
        </tr>
        <tr>
            <td>Дополнительные каналы соцсети</td>
            <td>129 000 сум</td>
        </tr>
        <tr>
            <td>Дополнительная воронка</td>
            <td>129 000 сум</td>
        </tr>
        <tr>
            <td>SMS - Рассылка</td>
            <td class="check">✓</td>
        </tr>
        <tr>
            <td>Складской учет и касса</td>
            <td>129 000 сум</td>
        </tr>
        <tr>
            <td>Интеграция с 1С</td>
            <td>*** сум</td>
        </tr>
    </table>
</div>

<!-- ========== PAGE 3 ========== -->
<div class="page-3">
    <div class="page-2-header">
        <img class="page-2-logo" src="https://billing-back.shamcrm.com/img/logoWithText.png" alt="SHAM CRM">
        <div class="page-2-link">*подробнее: <a href="https://shamcrm.com" target="_blank">shamcrm.com</a></div>
    </div>

    <div class="page-3-content">
        <h2 class="section-title">Итоговая стоимость</h2>

        <div class="total-section">
            <table class="total-table">
                <tr>
                    <td>Тариф "VIP" за 6 мес.</td>
                    <td>10 200 000 сум</td>
                </tr>
                <tr>
                    <td>Дополнительные модули за 6 мес.</td>
                    <td>4 716 000 сум</td>
                </tr>
                <tr>
                    <td>Подключение и внедрения</td>
                    <td>36 000 000 сум</td>
                </tr>
            </table>
            <div class="total-row">
                <span>Итог:</span> 50 916 000 сум
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
                <div class="contact-value">+998 78 555 7416</div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">
                    <img src="https://billing-back.shamcrm.com/img/world.png" alt="Web">
                </div>
                <div class="contact-value">shamcrm.com</div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">
                    <img src="https://billing-back.shamcrm.com/img/telegram.png" alt="Telegram">
                </div>
                <div class="contact-value">@shamcrm_uz</div>
            </div>
        </div>
    </div>

    <div class="validity-line">
        Предложение действительно до: <span></span>
    </div>
</div>
</body>
</html>
