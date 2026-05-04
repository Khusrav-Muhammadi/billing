<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добро пожаловать в shamCRM</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            margin: 0;
            padding: 24px;
            background: linear-gradient(135deg, #f0f2ff 0%, #d8e0ff 100%);
            font-family: 'Inter', system-ui, sans-serif;
        }

        @keyframes fadeInUpBounce {
            0%   { opacity: 0; transform: translateY(50px) scale(0.95); }
            60%  { transform: translateY(-8px) scale(1.02); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }


        .liquid-glass {
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(26px) saturate(190%);
            -webkit-backdrop-filter: blur(26px) saturate(190%);
            border: 1px solid rgba(255, 255, 255, 0.65);
            box-shadow: 0 30px 60px -15px rgba(0,0,0,0.18),
            inset 0 8px 25px rgba(255,255,255,0.78),
            inset 0 -6px 18px rgba(0,0,0,0.1);
            animation: fadeInUpBounce 0.95s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            transition: all 0.55s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .telegram-banner {
            background: rgba(255,255,255,0.09);
            backdrop-filter: blur(20px) saturate(170%);
            border: 1px solid rgba(41, 168, 235, 0.45);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .telegram-banner:hover {
            transform: translateY(-6px);
            border-color: #29A8EB;
            box-shadow: 0 20px 45px rgba(41, 168, 235, 0.25);
        }

    </style>
</head>
<body>

@php
    $siteUrl = 'https://' . $client->sub_domain . '.' . env('APP_DOMAIN');
    $accountId = $id ?? $client->order_number ?? $client->id ?? '';
@endphp

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 640px; margin: 0 auto;">
    <tr>
        <td>

            <!-- ШАПКА -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background: linear-gradient(135deg, #1B1F3B 0%, #2A2F5C 45%, #7C83FF 100%);
                    border-radius: 32px 32px 0 0; padding: 36px 32px 28px;">
                <tr>
                    <td style="text-align: center;">
                        <img src="https://fingroupcrm-back.shamcrm.com/storage/TaskFiles/RUV5XQqT6oC0NI9VQeSQCksbwYv4EFqYyIpZ4Uke.png" alt="" width="90" height="80">
                    </td>
                </tr>
            </table>

            <!-- Основной контент -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" class="liquid-glass"
                   style="padding: 36px 32px 32px; border-radius: 0 0 32px 32px;">
                <tr>
                    <td>
                        <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #1B1F3B;">
                            Здравствуйте, {{ $client->name }}! 👋
                        </h1>

                        <p style="margin: 0 0 20px; font-size: 16.5px; color: #333; line-height: 1.75;">
                            Рады приветствовать вас в системе shamCRM!<br>
                            Мы уверены, что наш сервис поможет вам автоматизировать процессы, ускорить продажи и вывести ваш бизнес на новый уровень.
                        </p>

                        <!-- Блок данных для входа -->
                        <p style="margin: 0 0 10px; font-size: 15.5px; font-weight: 600; color: #1B1F3B;">
                            Ваши данные для входа:
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background: #ffffff; border: 1px solid #dfe4ff; border-radius: 18px; margin: 0 0 24px;">
                            <tr>
                                <td style="padding: 18px 20px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="padding: 0 0 8px; font-size: 13px; line-height: 18px; color: #6b6f86;">
                                                Ссылка
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0 0 16px; border-bottom: 1px solid #edf0ff;">
                                                <a href="{{ $siteUrl }}" style="display: block; color: #5661f6; font-size: 15px; font-weight: 700; line-height: 22px; text-decoration: none; word-break: break-all;">
                                                    {{ $siteUrl }}
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 14px 0 8px; font-size: 13px; line-height: 18px; color: #6b6f86;">
                                                Логин
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0 0 16px; border-bottom: 1px solid #edf0ff; color: #1B1F3B; font-size: 15px; font-weight: 700; line-height: 22px; word-break: break-word;">
                                                admin
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 14px 0 8px; font-size: 13px; line-height: 18px; color: #6b6f86;">
                                                Пароль
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0 0 16px; border-bottom: 1px solid #edf0ff; color: #1B1F3B; font-family: 'Courier New', Courier, monospace; font-size: 15px; font-weight: 700; line-height: 22px; letter-spacing: 0.5px; word-break: break-all;">
                                                {{ $password }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 14px 0 8px; font-size: 13px; line-height: 18px; color: #6b6f86;">
                                                ID
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0; color: #1B1F3B; font-size: 15px; font-weight: 700; line-height: 22px; word-break: break-word;">
                                                {{ $accountId }}
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- Telegram баннер -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td class="telegram-banner" style="border-radius: 18px; padding: 16px 20px;">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 12px;">
                                        <span style="font-size: 32px;">💬</span>
                                        <div>
                                            <div style="color: #1B1F3B ; font-weight: 600; font-size: 16.5px;">Telegram-канал shamCRM</div>
                                            <div style="color: #29A8EB; font-size: 14.5px;">Анонсы, кейсы, обновления и закрытые материалы shamCRM</div>
                                        </div>
                                    </div>
                                    <a href="https://t.me/brand_shamCRM"
                                       style="display: block; margin-top: 14px; background: #29A8EB; color: white; text-align: center; padding: 13px; border-radius: 12px; font-weight: 700; text-decoration: none; font-size: 15px;">
                                        Перейти на канал
                                    </a>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <!-- Финальная строка -->
            <p style="text-align: center; color: #555; font-size: 15px; line-height: 1.7; margin: 18px 0 12px;">
                С shamCRM ваш бизнес работает быстрее, а контроль становится проще.<br>
                Мы рядом, чтобы вы сосредоточились на главном — <strong>развитии</strong>.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="text-align: center;">
                <tr>
                    <td style="padding: 4px 0;">
                        <span style="display: inline-flex; align-items: center; gap: 7px; font-size: 14px; color: #555;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7C83FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            <a href="https://www.shamcrm.com" style="color: #555; text-decoration: none;">www.shamcrm.com</a>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;">
                        <span style="display: inline-flex; align-items: center; gap: 7px; font-size: 14px; color: #555;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7C83FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.37 2 2 0 0 1 3.58 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.56a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            +998 555 888 100
                        </span>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>
</html>
