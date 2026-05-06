<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{{ $variant['title'] ?? 'Демо-период завершился' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,sans-serif;line-height:1.6;background:#f4f6fb;margin:0;padding:20px;color:#273044}
        .container{max-width:640px;background:#fff;margin:auto;padding:28px;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
        .header{font-size:24px;font-weight:700;color:#111827;margin-bottom:14px;text-align:center}
        .notice{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin:20px 0;color:#1e3a8a}
        .accent{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin:18px 0}
        .button-wrap{text-align:center;margin:24px 0}
        .button{display:inline-block;background:#2563eb;color:#fff!important;text-decoration:none;border-radius:8px;padding:13px 22px;font-weight:700}
        .footer{margin-top:24px;text-align:center;color:#667085;font-size:14px}
        a{color:#2563eb;text-decoration:none}
    </style>
</head>
<body>
<div class="container">
    <div class="header">{{ $variant['title'] ?? 'Демо-период завершился' }}</div>

    <p>Здравствуйте, {{ $client->name ?? 'клиент' }}!</p>

    <p>{{ $variant['lead'] ?? 'Спасибо, что протестировали shamCRM.' }}</p>

    <div class="notice">
        Демо-доступ завершился {{ $daysAfterExpiration }} {{ $daysAfterExpiration === 1 ? 'день' : 'дней' }} назад.
    </div>

    <div class="accent">
        {{ $variant['accent'] ?? 'Мы готовы помочь с подключением.' }}
    </div>

    <div class="button-wrap">
        <a class="button" href="https://shamcrm.com">Связаться и подключить</a>
    </div>

    <p>Спасибо, что доверились shamCRM. Будем рады помочь вам запустить систему уже в рабочем режиме.</p>

    <div class="footer">
        <a href="https://shamcrm.com">www.shamcrm.com</a><br>
        +998-55-588-81-00
    </div>
</div>
</body>
</html>
