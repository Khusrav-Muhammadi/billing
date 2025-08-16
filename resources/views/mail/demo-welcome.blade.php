<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добро пожаловать в shamCRM — демо уже в работе</title>
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
    <div class="header">Добро пожаловать в shamCRM</div>
    <div class="content">
        <p>Здравствуйте, {{ $client->name }}!</p>

        <p>
            Благодарим вас за интерес к <strong>shamCRM</strong>. Мы рады сотрудничеству!
            Мы уже создаём для вас демо-доступ и вскоре отправим письмо с логином и паролем.
        </p>

        @if($client->sub_domain)
            <p style="text-align:center;margin:18px 0;">
                <a class="btn" href="https://shamcrm.com">Перейти на сайт</a>
            </p>
        @else
            <p style="text-align:center;margin:18px 0;">
                <a class="btn" href="https://shamcrm.com">Перейти на сайт</a>
            </p>
        @endif

        <p class="muted" style="text-align:center;">
            Если у вас появятся вопросы — просто ответьте на это письмо.
        </p>
    </div>

    <div class="footer">
        🌐 <a href="https://shamcrm.com">www.shamcrm.com</a><br>
        📞 +998-77-375-68-68<br>
        🏛 г. Ташкент, Яккасарайский район, ул. Мирабад, 10
    </div>
</div>
</body>
</html>
