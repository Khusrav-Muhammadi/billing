<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Доступ к shamCRM — аккаунт уже создан</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,sans-serif;line-height:1.6;background-color:#f4f4f4;margin:0;padding:20px}
        .container{max-width:600px;background:#fff;padding:20px;margin:auto;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,.1)}
        .header{font-size:20px;font-weight:bold;margin-bottom:16px;text-align:center}
        .content{font-size:16px;color:#333}
        .info{background:#f8f8f8;padding:12px;border-radius:6px;margin:14px 0}
        .footer{margin-top:20px;font-size:14px;text-align:center;color:#666}
        a{color:#2a7ae2;text-decoration:none}
        .btn{display:inline-block;padding:12px 18px;border-radius:6px;background:#2a7ae2;color:#fff !important;font-weight:bold}
        .muted{color:#666;font-size:14px}
    </style>
</head>
<body>
<div class="container">
    <div class="header">Доступ к системе shamCRM</div>
    <div class="content">
        <p>Здравствуйте, {{ $client->name }}!</p>

        <p>
            Вы запросили доступ к <strong>shamCRM</strong>, однако для этого email уже существует аккаунт.
            Ниже — основные данные вашей CRM:
        </p>

        <div class="info">
            @if($client->sub_domain)
                <p><strong>Адрес CRM:</strong>
                    <a href="https://{{ $client->sub_domain }}.{{ env('APP_DOMAIN') }}">
                        https://{{ $client->sub_domain }}.{{ env('APP_DOMAIN') }}
                    </a>
                </p>
            @endif
            <p><strong>Email (логин):</strong> {{ $client->email }}</p>
            @if($client->phone)
                <p><strong>Телефон:</strong> {{ $client->phone }}</p>
            @endif
            @if($client->tariff?->name)
                <p><strong>Тариф:</strong> {{ $client->tariff->name }}</p>
            @endif
            @if($client->partner?->name)
                <p><strong>Ваш партнёр:</strong> {{ $client->partner->name }}</p>
            @endif
        </div>

        <p class="muted">
            По соображениям безопасности пароль не отображается в письме. Если вы его забыли — вы можете восстановить доступ:
        </p>


        <p class="muted" style="text-align:center;">
            Если запрос отправляли не вы — просто игнорируйте это письмо.
        </p>
    </div>

    <div class="footer">
        🌐 <a href="https://shamcrm.com">www.shamcrm.com</a><br>
        📞 +998-55-588-81-00<br>
        🏛 г. Ташкент, Яккасарайский район, ул. Мирабад, 10
    </div>
</div>
</body>
</html>
