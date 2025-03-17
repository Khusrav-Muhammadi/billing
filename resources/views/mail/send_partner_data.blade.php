<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добро пожаловать в shamCRM</title>
    <style>
        .container {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
        }
        .content {
            text-align: left;
        }
        .info {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
            text-align: center;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="content">
        <p><strong>Уважаемый партнёр, {{ $partner->name }}!</strong></p>
        <p>Благодарим Вас за сотрудничество с <strong>shamCRM</strong>! Мы рады приветствовать Вас в нашей системе и уверены, что вместе сможем достичь больших успехов.</p>
        <p>Для начала работы предоставляем Ваши учетные данные для входа в партнерский портал:</p>
        <div class="info">
            <p><strong>🔗 Адрес:</strong> <a href="https://partners.shamcrm.com/">https://partners.shamcrm.com/</a></p>
            <p><strong>📧 Почта:</strong> {{ $partner->email }}</p>
            <p><strong>🔑 Пароль:</strong> {{ $password }}</p>
        </div>
        <p>В партнерском кабинете Вы сможете управлять клиентами, получать актуальные предложения и отслеживать эффективность сотрудничества. Если у вас возникнут вопросы, наша команда всегда готова помочь!</p>
    </div>
    <div class="footer">
        🌍 <a href="https://www.softtech-group.com">www.softtech-group.com</a> <br>
        📞 +998-77-375-68-68 <br>
        📍 г. Ташкент, Яккасарайский район, ул. Мирабад, 10
    </div>
</div>
</body>
</html>

