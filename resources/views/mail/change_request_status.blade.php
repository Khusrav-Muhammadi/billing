<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обновление статуса заявки</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7fc;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .email-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .email-header {
            background-color: #2a3d66;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .email-body {
            padding: 20px;
        }
        .email-body p {
            font-size: 16px;
            line-height: 1.6;
        }
        .email-body a {
            color: #007bff;
            text-decoration: none;
        }
        .email-footer {
            background-color: #f1f1f1;
            padding: 10px;
            text-align: center;
            font-size: 14px;
            color: #777;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="email-header">
        <h1>Обновление статуса заявки</h1>
    </div>
    <div class="email-body">
        <p>Здравствуйте, <strong>{{ $partner->name }}</strong>,</p>

        @if($author->role == 'partner')
            <p>Партнёр <strong>{{ $author->name }}</strong> обновил статус заявки <strong>{{ $client->name }}</strong>.</p>
            <p>Вы можете просмотреть обновленный статус заявки, перейдя по следующей ссылке:</p>
        @else
            <p>Статус вашей заявки <strong>{{ $client->name }}</strong> был изменен.</p>
            <p>Пожалуйста, войдите в систему, чтобы узнать подробности о статусе заявки.</p>
        @endif

        <p>
            <a href="https://partners.shamcrm.com/" target="_blank">Перейти к заявке</a>
        </p>
    </div>
    <div class="email-footer">
        <p>Спасибо, что выбрали нашу платформу!</p>
        <p>С уважением, команда ShamCRM.</p>
    </div>
</div>
</body>
</html>
