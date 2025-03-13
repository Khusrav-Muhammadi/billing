<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подключение к партнерской программе</title>
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
    </style>
</head>
<body>
<div class="container">
    <div class="content">
        <p>Уважаемый коллега, {{ $partner->name }}»!</p>
        <p>Благодарим Вас за выбор системы “shamCRM”! Мы уверены, что наше сотрудничество будет долгим и продуктивным.</p>
        <p>Для начала работы предоставляем Ваши учетные данные для регистрации:</p>
        <div class="info">
            <p><strong>Домен:</strong> https://{{ $client->sub_domain }}.{{ env('APP_DOMAIN') }}</p>
            <p><strong>Логин:</strong> admin</p>
            <p><strong>Пароль:</strong> {{ $password }}</p>
        </div>
        <p>Если у Вас возникнут вопросы или потребуется помощь, Вы можете обратиться к нашему региональному партнеру в Таджикистане - ООО «Молия гурух».</p>
    </div>
    <div class="footer">
        🌐 <a href="https://www.softtech-group.com">www.softtech-group.com</a> <br>
        📞 +998-77-375-68-68 <br>
        🏛 г.Ташкент, Яккасарайский район, Богсарай, улица Мирабад 10
    </div>
</div>
</body>
</html>
