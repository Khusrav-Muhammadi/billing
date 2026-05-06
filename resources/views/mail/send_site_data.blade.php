<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>shamCRM - Доступ к системе</title>
    <style>
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse !important; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }

        body {
            margin: 0; padding: 0;
            background-color: #f4f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        .wrap { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 32px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }

        .header {
            background: linear-gradient(135deg, #1B1F3B 0%, #2A2F5C 45%, #7C83FF 100%);
            padding: 15px 32px;
            text-align: center;
        }

        .header-img {
            max-width: 100px;
            width: 100%;
            height: auto;
            display: inline-block;
        }

        .body-card { padding: 40px 32px 32px; }

        .welcome-text {
            margin: 0 0 20px;
            font-size: 16px;
            color: #333;
            line-height: 1.75;
            text-align: justify;
            text-justify: inter-word;
        }

        .data-card {
            background: #f8faff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px 24px;
            margin-bottom: 25px;
        }

        .field-table { width: 100%; }
        .field-row-td {
            padding: 12px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .field-table tr:last-child .field-row-td { border-bottom: none; }

        .field-value { font-size: 15px; color: #1B1F3B; font-weight: 500; word-break: break-all; }
        .field-value strong { font-weight: 700; color: #1B1F3B; }
        .field-value a { color: #7C83FF; text-decoration: none; font-weight: 600; }

        .id-instruction { font-size: 12px; color: #718096; font-weight: 400; line-height: 1.3; margin-top: 4px; }

        .warning-box { border: 2px solid #feb2b2; background-color: #fff5f5; border-radius: 16px; padding: 20px; margin-bottom: 25px; }
        .warning-text {
            color: #c53030;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            text-align: justify;
            text-justify: inter-word;
        }
        .warning-title { font-weight: 800; text-transform: uppercase; display: block; margin-bottom: 5px; }

        .tg-banner { border: 1px solid rgba(41,168,235,0.4); border-radius: 18px; padding: 16px 20px; background: rgba(41,168,235,0.04); }
        .tg-table { width: 100%; }
        .tg-text-side { padding-left: 12px; }
        .tg-btn { display: block; background: #29A8EB; color: white !important; text-align: center; padding: 13px; border-radius: 12px; font-weight: 700; text-decoration: none; font-size: 15px; margin-top: 14px; }

        .footer-info { text-align: center; padding: 0 32px 30px; }

        @media screen and (max-width: 600px) {
            .wrap { width: 100% !important; margin: 0 !important; border-radius: 0 !important; }
            .body-card { padding: 25px 20px !important; }
            .data-card { padding: 12px 15px !important; }
            .welcome-text, .warning-text { text-align: left !important; }
        }
    </style>
</head>
<body>

<div class="wrap">
    <div class="header">
        <img src="https://fingroupcrm-back.shamcrm.com/storage/TaskFiles/RUV5XQqT6oC0NI9VQeSQCksbwYv4EFqYyIpZ4Uke.png"
             alt="shamCRM"
             class="header-img" />
    </div>

    <div class="body-card">
        <h1 style="margin: 0 0 10px; font-size: 26px; font-weight: 700; color: #1B1F3B;">Здравствуйте, {{ $client->name }}!</h1>

        <p class="welcome-text">
            Рады приветствовать Вас в системе shamCRM!
            Мы уверены, что наш сервис поможет Вам автоматизировать процессы, ускорить продажи и вывести Ваш бизнес на новый уровень.
        </p>

        <p style="margin: 0 0 10px; font-size: 15px; font-weight: 600; color: #1B1F3B;">Ваши данные для входа:</p>

        @php
            $siteUrl = 'https://' . $client->sub_domain . '.' . env('APP_DOMAIN');
            $accountId = $id ?? $client->order_number ?? $client->id ?? '';
        @endphp

        <div class="data-card">
            <table class="field-table" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td class="field-row-td">
                        <div class="field-value">
                            <strong>Ссылка:</strong> <a href="{{ $siteUrl }}">{{ $siteUrl }}</a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="field-row-td">
                        <div class="field-value">
                            <strong>Логин:</strong> admin
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="field-row-td">
                        <div class="field-value">
                            <strong>Пароль:</strong> {{ $password }}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="field-row-td">
                        <div class="field-value">
                            <strong>ID:</strong> {{ $accountId }}
                        </div>
                        <div class="id-instruction">укажите этот ID при оплате, иначе баланс не пополнится</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="warning-box">
            <p class="warning-text">
                <span class="warning-title">⚠️ Важное уведомление:</span>
                Оплата принимается только в безналичной форме на расчетный счет компании или через официальных партнеров. Передача наличных денежных средств сотрудникам компании или на их карту <strong>ЗАПРЕЩЕНА</strong>. В случае нарушения данного правила компания не несет ответственность.
            </p>
        </div>

        <div class="tg-banner">
            <table class="tg-table" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td class="tg-logo-td" width="35" valign="center">
                        <img src="https://fingroupcrm-back.shamcrm.com/storage/TaskFiles/YFxOCBSZToOeK8vIUVhjE3Vaxd7fclCLYTuiKKkk.png"
                             width="35"
                             style="width: 35px; height: auto; display: block; margin: 0 auto;"
                             alt="TG" />
                    </td>
                    <td class="tg-text-side" valign="center">
                        <div style="color: #1B1F3B; font-weight: 600; font-size: 16px; line-height: 1.2;">Telegram-канал shamCRM</div>
                        <div style="color: #29A8EB; font-size: 14px; margin-top: 3px; line-height: 1.3;">Анонсы, кейсы, обновления и закрытые материалы shamCRM</div>
                    </td>
                </tr>
            </table>
            <a href="https://t.me/brand_shamCRM" class="tg-btn">Перейти на канал</a>
        </div>
    </div>

    <div class="footer-info">
        <p style="color: #555; font-size: 15px; line-height: 1.7; margin: 0 0 20px;">
            С shamCRM Ваш бизнес работает быстрее, а контроль становится проще.<br />
            Мы рядом, чтобы Вы сосредоточились на главном — <strong>развитии</strong>.
        </p>
        <div style="font-size: 14px; color: #718096;">
            <a href="https://www.shamcrm.com" style="color: #7C83FF; text-decoration: none; font-weight: 600;">www.shamcrm.com</a>
            &nbsp; | &nbsp;
            <span style="font-weight: bold; color: #1B1F3B;">+998 555 888 100</span>
        </div>
    </div>
</div>

</body>
</html>
