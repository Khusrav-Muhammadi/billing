<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Доступ к системе shamCRM</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .content {
            font-size: 16px;
            color: #333;
        }
        .info {
            background: #f8f8f8;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .footer {
            margin-top: 20px;
            font-size: 14px;
            text-align: center;
            color: #666;
        }
        a {
            color: #2a7ae2;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="content">
        <p>Здравствуйте, {{ $client->name }}! Рады приветствовать вас в системе shamCRM!</p>

        <p>Благодарим вас за интерес к системе <strong>shamCRM</strong>! Мы уверены, что наш сервис поможет вам автоматизировать процессы, ускорить продажи и вывести ваш бизнес на новый уровень.</p>

        <p>Ниже вы найдете ваши данные для входа:</p>
        <div class="info">
            <p><strong>Ссылка:</strong> <a href="https://{{ $client->sub_domain }}.{{ env('APP_DOMAIN') }}">https://{{ $client->sub_domain }}.{{ env('APP_DOMAIN') }}</a></p>
            <p><strong>Логин:</strong> admin</p>
            <p><strong>Пароль:</strong> {{ $password }}</p>
        </div>

        @if($client->partner)
            <p>Если у вас возникнут вопросы или потребуется помощь, вы можете обратиться к нашему региональному партнёру — {{ $client->partner->name }}.</p>
        @endif

        <p>С <strong>shamCRM</strong> ваш бизнес работает быстрее, а контроль становится проще. Мы рядом, чтобы вы сосредоточились на главном — развитии!</p>
    </div>

    <div class="footer">
        🌐 <a href="https://www.shamcrm.com">www.shamcrm.com</a><br>
        📞 +998-77-375-68-68<br>
        🏛 г. Ташкент, Яккасарайский район, ул. Мирабад, 10
    </div>
</div>
</body>
</html>
