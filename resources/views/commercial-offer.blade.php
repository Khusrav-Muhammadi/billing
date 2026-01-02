<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Коммерческое предложение | SHAM CRM</title>

    @php
        $isPdf = $isPdf ?? false;
        $img = fn(string $file) => $isPdf ? public_path('img/'.$file) : asset('img/'.$file);
        $bgMain = $img('main_backgoun.png');
        $bgBlue = $img('blue background.png');
        $logo = $img('logo.png');
        $iconClient = $img('clients.png');
        $iconManager = $img('manager.png');
        $iconCalendar = $img('calendar.png');
    @endphp

    @unless($isPdf)
        <style>
            @font-face {
                font-family: 'Cygrotesk';
                src: url('{{ asset('img/CYGROTESK-KEYMEDIUM.OTF') }}') format('opentype');
                font-weight: 500;
                font-style: normal;
            }

            @font-face {
                font-family: 'Cygrotesk';
                src: url('{{ asset('img/CYGROTESK-KEYBOLD.OTF') }}') format('opentype');
                font-weight: 700;
                font-style: normal;
            }
        </style>
    @endunless

    <style>
        :root {
            --brand-blue: #2f4df6;
            --text-primary: #0b1b35;
            --text-secondary: #1f2c45;
            --hero-bg-opacity: 0.8; /* базовая прозрачность */
            --hero-start-opacity: 0.92; /* слева плотнее */
            --hero-end-opacity: 0.38;   /* справа прозрачнее */
            --icon-bg-opacity: 0.6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: {{ $isPdf ? "'DejaVu Sans', Arial, sans-serif" : "'Cygrotesk','Inter',Arial,sans-serif" }};
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
            background: url('{{ $bgMain }}') center/cover no-repeat;
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

        .hero {
            position: relative;
            width: 100%;
            max-width: 610px;
            border-radius: 64px;
            padding: 36px 40px;
            text-align: center;
            margin-bottom: 70px;
            box-shadow: 0 8px 32px rgba(47, 77, 246, 0.25);
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg,
                rgba(47, 77, 246, var(--hero-start-opacity)) 0%,
                rgba(47, 77, 246, var(--hero-bg-opacity)) 45%,
                rgba(47, 77, 246, var(--hero-end-opacity)) 100%);
            border-radius: inherit;
            z-index: 0;
        }

        .hero-title {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 55px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.3;
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

        /* Solid blue icon background */
        .icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 50%;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(47, 77, 246, 0.2);
        }

        .icon::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(47, 77, 246, var(--icon-bg-opacity));
            border-radius: 50%;
            z-index: 0;
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
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <img class="logo-mark" src="{{ $logo }}" alt="SHAM CRM logo">

    </div>

    <div class="hero">
        <h1 class="hero-title">КОММЕРЧЕСКОЕ<br>ПРЕДЛОЖЕНИЕ</h1>
    </div>

    <div class="meta">
        <div class="meta-item">
        <div class="icon">
            <img src="{{ $iconClient }}" alt="Клиент"
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
            <img src="{{ $iconManager }}" alt="Менеджер"
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
            <img src="{{ $iconCalendar }}" alt="Дата"
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
</body>
</html>
