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

        body { margin: 0; padding: 0; background-color: #f4f7fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }

        @media screen and (max-width: 600px) {
            .wrap-table { width: 100% !important; max-width: 100% !important; border-radius: 0 !important; margin: 0 !important; }
            .body-card { padding: 25px 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7fa;">

@php
    $siteUrl = 'https://' . $client->sub_domain . '.' . env('APP_DOMAIN');
    $accountId = $id ?? $client->order_number ?? $client->id ?? '';
@endphp

<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f7fa">
    <tr>
        <td align="center" style="padding: 20px 0;">

            <table class="wrap-table" width="600" align="center" cellpadding="0" cellspacing="0" border="0" style="width: 600px; max-width: 600px; background-color: #ffffff; border-radius: 32px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); margin: 0 auto;">

                <!-- ШАПКА -->
                <tr>
                    <td align="center" style="background: #1B1F3B linear-gradient(135deg, #1B1F3B 0%, #2A2F5C 45%, #7C83FF 100%); padding: 20px 32px;">
                        <img src="https://fingroupcrm-back.shamcrm.com/storage/TaskFiles/RUV5XQqT6oC0NI9VQeSQCksbwYv4EFqYyIpZ4Uke.png" alt="shamCRM" width="100" style="width: 100px; max-width: 100px; height: auto; display: block; margin: 0 auto;" />
                    </td>
                </tr>

                <!-- ОСНОВНОЙ КОНТЕНТ -->
                <tr>
                    <td class="body-card" style="padding: 40px 32px 32px;">

                        <h1 style="margin: 0 0 10px; font-size: 26px; font-weight: 700; color: #1B1F3B; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">Здравствуйте, {{ $client->name }}!</h1>

                        <p style="margin: 0 0 20px; font-size: 16px; color: #333; line-height: 1.75; text-align: justify; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                            Рады приветствовать Вас в системе shamCRM!
                            Мы уверены, что наш сервис поможет Вам автоматизировать процессы, ускорить продажи и вывести Ваш бизнес на новый уровень.
                        </p>

                        <p style="margin: 0 0 15px; font-size: 15px; font-weight: 600; color: #1B1F3B; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">Ваши данные для входа:</p>

                        <!-- БЛОК ДАННЫХ -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8faff; border: 1px solid #e2e8f0; border-radius: 16px; margin-bottom: 25px;">
                            <tr>
                                <td style="padding: 10px 20px;">

                                    <!-- Ссылка -->
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-bottom: 1px solid #edf2f7;">
                                        <tr>
                                            <td style="padding: 14px 0; font-size: 15px; color: #1B1F3B; font-family: -apple-system, BlinkMacSystemFont, sans-serif; word-break: break-all;">
                                                <strong style="font-weight: 700;">Ссылка:</strong> <a href="{{ $siteUrl }}" style="color: #7C83FF; text-decoration: none; font-weight: 600;">{{ $siteUrl }}</a>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Логин -->
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-bottom: 1px solid #edf2f7;">
                                        <tr>
                                            <td style="padding: 14px 0; font-size: 15px; color: #1B1F3B; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                                                <strong style="font-weight: 700;">Логин:</strong> admin
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Пароль -->
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-bottom: 1px solid #edf2f7;">
                                        <tr>
                                            <td style="padding: 14px 0; font-size: 15px; color: #1B1F3B; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                                                <strong style="font-weight: 700;">Пароль:</strong> {{ $password }}
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- ID -->
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="padding: 14px 0; font-size: 15px; color: #1B1F3B; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                                                <strong style="font-weight: 700;">ID:</strong> {{ $accountId }}
                                                <div style="font-size: 12px; color: #718096; font-weight: 400; line-height: 1.3; margin-top: 4px;">укажите этот ID при оплате, иначе баланс не пополнится</div>
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>
                        </table>

                        <!-- БЛОК ПРЕДУПРЕЖДЕНИЯ -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #fff5f5; border: 2px solid #feb2b2; border-radius: 16px; margin-bottom: 25px;">
                            <tr>
                                <td style="padding: 20px;">
                                    <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 5px; color: #c53030; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">⚠️ Важное уведомление:</div>
                                    <div style="color: #c53030; font-size: 14px; line-height: 1.6; text-align: justify; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                                        Оплата принимается только в безналичной форме на расчетный счет компании или через официальных партнеров. Передача наличных денежных средств сотрудникам компании или на их карту <strong style="font-weight: 700;">ЗАПРЕЩЕНА</strong>. В случае нарушения данного правила компания не несет ответственность.
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- TELEGRAM БАННЕР -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: rgba(41,168,235,0.04); border: 1px solid rgba(41,168,235,0.4); border-radius: 18px;">
                            <tr>
                                <td style="padding: 16px 20px;">

                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 14px;">
                                        <tr>
                                            <td width="45" valign="middle" style="padding-right: 12px;">
                                                <img src="https://fingroupcrm-back.shamcrm.com/storage/TaskFiles/YFxOCBSZToOeK8vIUVhjE3Vaxd7fclCLYTuiKKkk.png" width="35" style="width: 35px; height: auto; display: block;" alt="TG" />
                                            </td>
                                            <td valign="middle">
                                                <div style="color: #1B1F3B; font-weight: 600; font-size: 16px; line-height: 1.2; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">Telegram-канал shamCRM</div>
                                                <div style="color: #29A8EB; font-size: 14px; margin-top: 3px; line-height: 1.3; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">Анонсы, кейсы, обновления и закрытые материалы</div>
                                            </td>
                                        </tr>
                                    </table>

                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td align="center" style="background-color: #29A8EB; border-radius: 12px;">
                                                <a href="https://t.me/brand_shamCRM" target="_blank" style="display: block; padding: 13px; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">Перейти на канал</a>
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- ФУТЕР -->
                <tr>
                    <td align="center" style="padding: 0 32px 30px;">
                        <p style="color: #555; font-size: 15px; line-height: 1.7; margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                            С shamCRM Ваш бизнес работает быстрее, а контроль становится проще.<br />
                            Мы рядом, чтобы Вы сосредоточились на главном — <strong style="color: #333;">развитии</strong>.
                        </p>
                        <div style="font-size: 14px; color: #718096; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                            <a href="https://www.shamcrm.com" style="color: #7C83FF; text-decoration: none; font-weight: 600;">www.shamcrm.com</a>
                            &nbsp; | &nbsp;
                            <span style="font-weight: bold; color: #1B1F3B;">+998 555 888 100</span>
                        </div>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
