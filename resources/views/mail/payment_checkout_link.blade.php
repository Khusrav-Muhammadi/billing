<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Ссылка на оплату SHAMCRM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,sans-serif;line-height:1.6;background-color:#f4f4f4;margin:0;padding:20px;color:#333}
        .container{max-width:640px;background:#fff;padding:24px;margin:auto;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,.08)}
        .header{font-size:22px;font-weight:bold;margin-bottom:16px;text-align:center;color:#1f2937}
        .box{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin:18px 0}
        .btn{display:inline-block;background:#1e3c72;color:#fff!important;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold}
        .muted{color:#667085;font-size:14px}
        .link{word-break:break-all;color:#2563eb}
        .footer{margin-top:22px;font-size:14px;text-align:center;color:#667085}
        a{color:#2563eb}
    </style>
</head>
<body>
@php
    $name = trim((string)($recipientName ?? ''));
    $sum = (string)($payment->sum ?? '');
    $displaySum = is_numeric($sum) ? number_format((float)$sum, 2, '.', ' ') : $sum;
    $currencyLabel = trim((string)($currency ?? ''));
@endphp
<div class="container">
    <div class="header">Ссылка на оплату SHAMCRM</div>

    <p>Здравствуйте{{ $name !== '' ? ', ' . $name : '' }}!</p>

    <p>
        Для оплаты услуг SHAMCRM перейдите по ссылке ниже.
    </p>

    <div class="box">
        <strong>Платёж №{{ $payment->id }}</strong><br>
        Сумма: {{ $displaySum }}{{ $currencyLabel !== '' ? ' ' . $currencyLabel : '' }}
    </div>

    <p style="text-align:center;">
        <a class="btn" href="{{ $checkoutUrl }}" target="_blank" rel="noopener">Перейти к оплате</a>
    </p>

    <p class="muted">
        Если кнопка не открывается, скопируйте ссылку в браузер:<br>
        <a class="link" href="{{ $checkoutUrl }}" target="_blank" rel="noopener">{{ $checkoutUrl }}</a>
    </p>

    <div class="footer">
        <a href="https://shamcrm.com">www.shamcrm.com</a><br>
        +998-55-588-81-00
    </div>
</div>
</body>
</html>
