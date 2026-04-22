<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Подтверждение оплаты</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: radial-gradient(1200px 600px at 20% 10%, rgba(30,60,114,.25), transparent 60%),
                        radial-gradient(900px 500px at 80% 0%, rgba(42,82,152,.22), transparent 55%),
                        linear-gradient(135deg, #0b1220, #0f1b33);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            color: #111827;
        }
        .card {
            width: 100%;
            max-width: 680px;
            background: rgba(255,255,255,.96);
            border-radius: 14px;
            box-shadow: 0 18px 60px rgba(0,0,0,.35);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.18);
        }
        .header {
            padding: 18px 18px 14px;
            background: linear-gradient(135deg, rgba(30,60,114,.12), rgba(42,82,152,.10));
            border-bottom: 1px solid #e5e7eb;
        }
        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }
        .brand-name {
            font-weight: 800;
            letter-spacing: .4px;
            color: #0f172a;
        }
        .badge {
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #111827;
            color: #fff;
            font-weight: 700;
        }
        .title {
            margin: 0;
            font-size: 20px;
            color: #0f172a;
        }
        .subtitle {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.45;
        }
        .content { padding: 18px; }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .info {
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
        }
        .label { font-size: 12px; color: #6b7280; }
        .value { margin-top: 4px; font-weight: 700; color: #111827; word-break: break-word; }
        .value-muted { margin-top: 4px; font-weight: 500; color: #111827; word-break: break-word; }
        .items {
            margin-top: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .items-header {
            padding: 12px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 700;
            color: #111827;
        }
        table { width: 100%; border-collapse: collapse; }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #eef2f7;
            font-size: 13px;
        }
        tr:last-child td { border-bottom: none; }
        .td-right { text-align: right; white-space: nowrap; }
        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-top: 1px solid #e5e7eb;
            background: #fcfcfd;
            font-weight: 800;
        }
        .notice {
            margin-top: 14px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #92400e;
            font-size: 13px;
            line-height: 1.5;
        }
        .notice strong { color: #7c2d12; }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn {
            appearance: none;
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            font-weight: 800;
            cursor: pointer;
            transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
            box-shadow: 0 10px 26px rgba(30,60,114,.35);
        }
        .btn-secondary {
            background: #eef2ff;
            color: #1f2a44;
            border: 1px solid #e5e7eb;
        }
        .btn:active { transform: translateY(1px); }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .footer {
            padding: 12px 18px 16px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.4;
        }
        .error {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            font-size: 13px;
        }
        @media (max-width: 640px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@php
    $currency = (string) ($offer?->payable_currency ?? $offer?->currency ?? '');
    $currency = $currency !== '' ? strtoupper($currency) : '';
    $sum = (string) $payment->sum;
    $displaySum = $sum;
    if (is_numeric($sum)) {
        $displaySum = number_format((float) $sum, 2, '.', ' ');
    }
    $partnerName = (string) ($offer?->partner?->name ?? '');
    $hasPartner = (bool) ($offer?->partner_id);
    $agreementUrl = 'https://shamcrm.com/agreement';
@endphp

<div class="card">
    <div class="header">
        <div class="brand">
            <div class="brand-name">SHAMCRM</div>
            <div class="badge">{{ $providerLabel }}</div>
        </div>
        <h1 class="title">Подтверждение оплаты</h1>
        <p class="subtitle">
            Перед переходом к оплате проверьте данные. Нажмите «Далее», чтобы открыть страницу оплаты в {{ $providerLabel }}.
        </p>
    </div>

    <div class="content">
        <div class="info">
            <div class="label">Сумма к оплате</div>
            <div class="value" style="font-size:20px;">
                {{ $displaySum }}{{ $currency ? (' ' . $currency) : '' }}
            </div>
            <div class="value-muted" style="font-size:12px; color:#6b7280; font-weight:500;">
                Платёж №{{ $payment->id }}
            </div>
        </div>

        <div class="items">
            <div class="items-header">Услуги</div>
            <table>
                <tbody>
                @forelse($items as $row)
                    <tr>
                        <td>{{ (string) $row->service_name }}</td>
                        <td class="td-right">
                            @php
                                $rowPrice = (string) $row->price;
                                $rowDisplay = $rowPrice;
                                if (is_numeric($rowPrice)) {
                                    $rowDisplay = number_format((float) $rowPrice, 2, '.', ' ');
                                }
                            @endphp
                            {{ $rowDisplay }}{{ $currency ? (' ' . $currency) : '' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" style="color:#6b7280;">Позиции не найдены.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            <div class="total">
                <div>Итого</div>
                <div>{{ $displaySum }}{{ $currency ? (' ' . $currency) : '' }}</div>
            </div>
        </div>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ $goUrl }}" style="margin:0;">
            @csrf

            <div class="notice">
                <strong>Важно.</strong>
                @if($hasPartner)

                    Нажимая «Далее», я подтверждаю, что ознакомил(а) клиента с услугами SHAMCRM и условиями оферты.
                    В случае отказа средства возврату не подлежат.
                @else
                    Нажимая «Далее», я подтверждаю, что ознакомлен(а) и согласен(на) с условиями публичной оферты SHAMCRM.
                @endif
                <div style="margin-top:8px;">
                    <a href="{{ $agreementUrl }}" target="_blank" rel="noopener" style="color:#92400e; font-weight:700;">
                        Ознакомиться с офертой
                    </a>
                </div>
                <div style="margin-top:10px; display:flex; gap:10px; align-items:flex-start;">
                    <input type="checkbox" id="consent" name="consent" value="1" style="margin-top:2px;">
                    <label for="consent" style="cursor:pointer;">
                        @if($hasPartner)
                            Я подтверждаю, что ознакомил(а) клиента с условиями оферты.
                        @else
                            Я принимаю условия оферты.
                        @endif
                    </label>
                </div>
            </div>

            <div class="actions">
                <button class="btn btn-primary" id="goBtn" type="submit">
                    Далее
                </button>
                <a class="btn btn-secondary" href="javascript:history.back()">
                    Назад
                </a>
            </div>
        </form>
    </div>

    <div class="footer">
        Если вы не инициировали этот платёж — просто закройте страницу.
    </div>
</div>

<script>
    (function () {
        const goBtn = document.getElementById('goBtn');
        const consent = document.getElementById('consent');
        if (!goBtn) return;

        const sync = () => {
            goBtn.disabled = !(consent && consent.checked);
        };

        if (consent) {
            consent.addEventListener('change', sync);
            sync();
        }
    })();
</script>
</body>
</html>
