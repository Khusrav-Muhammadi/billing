<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHAM CRM - Коммерческое предложение</title>
    <style>
        @font-face {
            font-family: 'Cy Grotesk';
            src: url({{asset('img/CYGROTESK-KEYMEDIUM.OTF')}}) format('opentype');
            font-weight: 500;
            font-style: normal;
        }

        @font-face {
            font-family: 'Cy Grotesk';
            src: url({{asset('img/CYGROTESK-KEYBOLD.OTF')}}) format('opentype');
            font-weight: 700;
            font-style: normal;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-blue: #2B4BFF;
            --dark-blue: #1a237e;
            --light-bg: #f7f6f5;
            --text-dark: #1a1a2e;
            --text-gray: #6b7280;
            --border-color: #e5e7eb;
            --icon-bg: rgba(69, 112, 255, 0.5);
        }

        body {
            font-family: 'Cy Grotesk', sans-serif;
            background: #f7f6f5;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .page {
            width: 794px;
            min-height: 1123px;
            margin: 0 auto;
            background: var(--light-bg);
            position: relative;
            overflow: hidden;
            page-break-after: always;
        }

        .cover-page {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 80px 60px 0;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 60px;
        }

        .logo-icon {
            width: 160px;
            height: 160px;
            margin-bottom: 20px;
        }

        .logo-icon svg {
            width: 100%;
            height: 100%;
        }

        .logo-text {
            display: flex;
            align-items: center;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .logo-text span:first-child {
            color: var(--text-dark);
        }

        .logo-text .crm-badge {
            background: var(--primary-blue);
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            margin-left: 4px;
        }

        .main-title {
            font-size: 52px;
            font-weight: 900;
            text-align: center;
            line-height: 1.2;
            margin-bottom: 80px;
            color: var(--text-dark);
        }

        .info-section {
            width: 100%;
            max-width: 400px;
        }

        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 40px;
        }

        .info-icon {
            width: 56px;
            height: 56px;
            background: var(--icon-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            flex-shrink: 0;
        }

        .info-icon svg {
            width: 28px;
            height: 28px;
            color: var(--primary-blue);
        }

        .info-icon img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }

        .info-label {
            font-size: 18px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .cover-decoration {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 100%;
            height: 150px;
            overflow: hidden;
        }

        .wave-shape {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 350px;
            height: 150px;
        }

        .wave-dark {
            fill: var(--dark-blue);
        }

        .wave-light {
            fill: var(--primary-blue);
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
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            border-bottom-left-radius: 80px;
        }

        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 50px;
        }

        .page-header img {
            height: 60px;
            width: auto;
        }

        .logo-container img {
            height: 200px;
            width: auto;
        }

        .tariff-header {
            display: flex;
            justify-content: flex-start;
            gap: 200px;
            margin-bottom: 30px;
        }

        .tariff-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .tariff-table {
            width: 100%;
            border: 2px solid var(--primary-blue);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 40px;
        }

        .table-header {
            display: flex;
            background: white;
            border-bottom: 1px solid var(--border-color);
        }

        .table-header-cell {
            padding: 20px 24px;
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 16px;
        }

        .table-header-cell:first-child {
            flex: 1;
        }

        .table-header-cell:last-child {
            width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkmark {
            width: 24px;
            height: 24px;
            color: var(--text-dark);
        }

        .table-row {
            display: flex;
            background: white;
            border-bottom: 1px solid var(--border-color);
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-cell {
            padding: 20px 24px;
            font-size: 16px;
            color: var(--text-dark);
        }

        .table-cell:first-child {
            flex: 1;
        }

        .table-cell:last-child {
            width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .feature-title {
            font-weight: 500;
        }

        .feature-subtitle {
            color: var(--text-gray);
            font-size: 14px;
            margin-top: 2px;
        }

        .summary-section {
            margin-top: 40px;
        }

        .summary-row {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 16px;
        }

        .bottom-decoration {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100px;
        }

        .bottom-wave {
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

        .cost-page .header-decoration {
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            border-bottom-left-radius: 80px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            color: var(--text-dark);
            margin-bottom: 40px;
            margin-top: 30px;
        }

        .cost-table {
            width: 100%;
            border: 2px solid var(--primary-blue);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 60px;
        }

        .cost-table .table-header {
            display: flex;
            background: white;
            border-bottom: 1px solid var(--border-color);
        }

        .cost-table .table-header-cell {
            padding: 20px 24px;
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 16px;
        }

        .cost-table .table-header-cell:first-child {
            flex: 1;
        }

        .cost-table .table-header-cell:last-child {
            width: 150px;
            text-align: right;
        }

        .cost-table .table-row {
            display: flex;
            background: white;
            border-bottom: 1px solid var(--border-color);
        }

        .cost-table .table-row:last-child {
            border-bottom: none;
        }

        .cost-table .table-cell {
            padding: 20px 24px;
            font-size: 16px;
            color: var(--text-dark);
        }

        .cost-table .table-cell:first-child {
            flex: 1;
        }

        .cost-table .table-cell:last-child {
            width: 150px;
            text-align: right;
        }

        .cost-table .table-row.total-row .table-cell {
            font-weight: 700;
            color: var(--primary-blue);
        }

        .tagline {
            text-align: center;
            margin-bottom: 40px;
        }

        .tagline-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.5;
        }

        .tagline-text span {
            color: var(--primary-blue);
        }

        .contacts-section {
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
            width: 56px;
            height: 56px;
            background: var(--icon-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .contact-icon svg {
            width: 26px;
            height: 26px;
            color: var(--primary-blue);
        }

        .contact-value {
            font-size: 14px;
            color: var(--text-dark);
            font-weight: 500;
        }

        .validity-notice {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .bottom-decoration-left {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 250px;
            height: 120px;
        }

        .bottom-decoration-right {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 250px;
            height: 120px;
        }

        @media print {
            .page {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }
        }

        @media screen {
            .page {
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
<!-- PAGE 1 - COVER -->
<div class="page cover-page">
    <div class="logo-container">
        <img src="{{asset('img/1.png')}}" alt="SHAM CRM">
    </div>

    <h1 class="main-title">КОММЕРЧЕСКОЕ<br>ПРЕДЛОЖЕНИЕ</h1>

    <div class="info-section">
        <div class="info-row">
            <div class="info-icon">
                <img src="{{asset('img/638336.png')}}" alt="">
            </div>
            <span class="info-label">Клиент:</span>
        </div>

        <div class="info-row">
            <div class="info-icon">
                <img src="{{asset('img/677333 копия.png')}}" alt="">
            </div>
            <span class="info-label">Менеджер:</span>
        </div>

        <div class="info-row">
            <div class="info-icon">
                <img src="{{asset('img/ФЫВ.png')}}" alt="">
            </div>
            <span class="info-label">Дата:</span>
        </div>
    </div>

    <div class="cover-decoration">
        <svg class="wave-shape" viewBox="0 0 350 150" preserveAspectRatio="none">
            <path class="wave-dark" d="M350 150 L350 0 C300 20 250 60 180 80 C110 100 50 90 0 150 Z"/>
            <path class="wave-light" d="M350 150 L350 30 C310 50 270 80 200 95 C130 110 70 100 0 150 Z"/>
        </svg>
    </div>
</div>

<!-- PAGE 2 - TARIFF -->
<div class="page tariff-page">
    <div class="header-decoration"></div>

    <div class="page-header">
        <img src="https://shamcrm.com/img/logoWithText.webp" alt="SHAM CRM">
    </div>

    <div class="tariff-header">
        <span class="tariff-title">Тариф shamCRM:</span>
        <span class="tariff-title">Срок:</span>
    </div>

    <div class="tariff-table">
        <div class="table-header">
            <div class="table-header-cell">Входит в тариф</div>
            <div class="table-header-cell">
                <svg class="checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
        </div>

        <div class="table-row">
            <div class="table-cell">
                <div class="feature-title">Воронка продаж</div>
            </div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">
                <div class="feature-title">Интеграция:</div>
                <div class="feature-subtitle">WhatsApp / Instagram / Telegram</div>
            </div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">
                <div class="feature-title">Управление задачами</div>
            </div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">
                <div class="feature-title">Календарь (встречи, записи)</div>
            </div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">
                <div class="feature-title">Мобильное приложение shamCRM</div>
            </div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">
                <div class="feature-title">Mini-app B2B (партнёры, дилеры)</div>
            </div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">
                <div class="feature-title">Mini-app B2C (клиенты, заявки)</div>
            </div>
            <div class="table-cell"></div>
        </div>
    </div>

    <div class="summary-section">
        <div class="summary-row">Пользователей в тарифе:</div>
        <div class="summary-row">Стоимость тарифа:</div>
    </div>

    <div class="bottom-decoration">
        <svg class="bottom-wave" viewBox="0 0 250 100" preserveAspectRatio="none">
            <path fill="#1a237e" d="M0 100 L0 0 C30 10 80 40 130 50 C180 60 220 50 250 100 Z"/>
            <path fill="#2B4BFF" d="M0 100 L0 20 C40 30 90 55 140 60 C190 65 230 55 250 100 Z"/>
        </svg>
    </div>
</div>

<!-- PAGE 3 - TOTAL COST -->
<div class="page cost-page">
    <div class="header-decoration"></div>

    <div class="page-header">
        <img src="https://shamcrm.com/img/logoWithText.webp" alt="SHAM CRM">
    </div>

    <h2 class="section-title">Итоговая стоимость</h2>

    <div class="cost-table">
        <div class="table-header">
            <div class="table-header-cell">Статья</div>
            <div class="table-header-cell">Сумма</div>
        </div>

        <div class="table-row">
            <div class="table-cell">Тариф shamCRM</div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">Доп. пользователи</div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">Внедрение и обучение</div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row">
            <div class="table-cell">Доп. опции</div>
            <div class="table-cell"></div>
        </div>

        <div class="table-row total-row">
            <div class="table-cell">ИТОГО</div>
            <div class="table-cell"></div>
        </div>
    </div>

    <div class="tagline">
        <p class="tagline-text"><span>shamCRM</span> — CRM, которая<br>реально внедряется и работает</p>
    </div>

    <div class="contacts-section">
        <div class="contact-item">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
            </div>
            <span class="contact-value">+998 78 555 7416</span>
        </div>

        <div class="contact-item">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="2" y1="12" x2="22" y2="12"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
            </div>
            <span class="contact-value">shamcrm.com</span>
        </div>

        <div class="contact-item">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                </svg>
            </div>
            <span class="contact-value">@shamcrm_uz</span>
        </div>
    </div>

    <p class="validity-notice">Предложение действительно ** дней</p>

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
