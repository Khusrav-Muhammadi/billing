<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Спасибо за оплату в shamCRM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,sans-serif;line-height:1.6;background-color:#f4f4f4;margin:0;padding:20px;color:#333}
        .container{max-width:680px;background:#fff;padding:24px;margin:auto;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,.08)}
        .header{font-size:22px;font-weight:bold;margin-bottom:16px;text-align:center;color:#1f2937}
        .muted{color:#667085;font-size:14px}
        .box{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin:18px 0}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f3f4f6;color:#374151}
        .right{text-align:right}
        .footer{margin-top:22px;font-size:14px;text-align:center;color:#667085}
        a{color:#2563eb;text-decoration:none}
    </style>
</head>
<body>
<div class="container">
    <div class="header">Спасибо, что доверились shamCRM</div>

    <p>Здравствуйте, {{ $clientName }}!</p>

    <p>
        Мы получили оплату за {{ $operationLabel }}. Спасибо за доверие — команда shamCRM уже продолжает работу с вашим аккаунтом.
    </p>

    @if(count($services) > 0)
        <div class="box">
            <strong>Подключенные услуги</strong>
            <table>
                <thead>
                <tr>
                    <th>Услуга</th>
                    <th class="right">Кол-во</th>
                    <th class="right">Сумма</th>
                </tr>
                </thead>
                <tbody>
                @foreach($services as $service)
                    <tr>
                        <td>{{ $service['name'] }}</td>
                        <td class="right">{{ $service['quantity'] }}</td>
                        <td class="right">{{ $service['formatted_amount'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($implementation)
        <div class="box">
            <strong>Внедрение</strong>
            <table>
                <tbody>
                <tr>
                    <td>{{ $implementation['name'] }}</td>
                    <td class="right">{{ $implementation['formatted_total'] }}</td>
                </tr>
                @if($implementation['extra_amount'] > 0)
                    <tr>
                        <td class="muted">Дополнительные услуги внедрения</td>
                        <td class="right muted">{{ number_format($implementation['extra_amount'], 2, ',', ' ') }} {{ $implementation['currency'] }}</td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    @endif

    @if(count($services) === 0 && !$implementation)
        <div class="box">
            Оплата успешно проведена. Если у вас появятся вопросы по составу услуг, просто ответьте на это письмо.
        </div>
    @endif

    <p class="muted">
        Если нужна помощь с настройкой или запуском, напишите нам — мы рядом.
    </p>

    <div class="footer">
        <a href="https://shamcrm.com">www.shamcrm.com</a><br>
        +998-77-375-68-68
    </div>
</div>
</body>
</html>
