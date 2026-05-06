<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Баланс shamCRM скоро закончится</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,sans-serif;line-height:1.6;background:#f4f4f4;margin:0;padding:20px;color:#333}
        .container{max-width:640px;background:#fff;margin:auto;padding:24px;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,.08)}
        .header{font-size:22px;font-weight:bold;text-align:center;color:#1f2937;margin-bottom:16px}
        .notice{background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px;margin:18px 0}
        .summary{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin:18px 0}
        .row{display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid #e5e7eb;padding:8px 0}
        .row:last-child{border-bottom:0}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th,td{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f3f4f6;color:#374151}
        .right{text-align:right}
        .value{font-weight:bold}
        .footer{margin-top:22px;text-align:center;color:#667085;font-size:14px}
        a{color:#2563eb;text-decoration:none}
    </style>
</head>
<body>
<div class="container">
    <div class="header">Баланс скоро закончится</div>

    <p>Здравствуйте, {{ $client->name ?? 'клиент' }}!</p>

    <div class="notice">
       Текущего баланса хватит на
        <strong>{{ $daysLeft }}</strong> {{ $daysLeft === 1 ? 'день' : 'дней' }}.
    </div>

    <div class="summary">
        <div class="row">
            <span>Текущий баланс</span>
            <span class="value">{{ number_format((float)$balance, 2, ',', ' ') }} {{ $currencyCode }}</span>
        </div>
    </div>

    <p>Чтобы услуги продолжили работать без остановки, пожалуйста, пополните баланс заранее.</p>

    <p>Спасибо, что пользуетесь shamCRM.</p>

    <div class="footer">
        <a href="https://shamcrm.com">www.shamcrm.com</a><br>
        +998-55-588-81-00
    </div>
</div>
</body>
</html>
