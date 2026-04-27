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

        .field-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .field-value {
            flex: 1;
            background: rgba(255,255,255,0.55);
            border: 1px solid rgba(124,131,255,0.25);
            border-radius: 12px;
            padding: 10px 110px 10px 14px;
            font-size: 15px;
            font-weight: 500;
            color: #1B1F3B;
            font-family: 'Inter', system-ui, sans-serif;
            letter-spacing: 0.2px;
            word-break: break-all;
            box-sizing: border-box;
            min-height: 42px;
            line-height: 1.4;
        }

        .field-value a {
            color: #7C83FF;
            text-decoration: none;
        }

        .copy-btn {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1.5px solid rgba(124, 131, 255, 0.35);
            background: rgba(255,255,255,0.85);
            color: #7C83FF;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Inter', system-ui, sans-serif;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
            outline: none;
            flex-shrink: 0;
        }

        .copy-btn:hover {
            background: rgba(124, 131, 255, 0.12);
            border-color: #7C83FF;
        }

        .copy-btn.copied {
            background: rgba(52, 199, 89, 0.13);
            border-color: #34C759;
            color: #34C759;
        }

        .copy-btn svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        #toast {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #1B1F3B;
            color: #fff;
            padding: 11px 22px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s, transform 0.25s;
            z-index: 9999;
            white-space: nowrap;
            box-shadow: 0 8px 24px rgba(0,0,0,0.22);
        }

        #toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body>

@php
    $siteUrl = 'https://' . $client->sub_domain . '.' . env('APP_DOMAIN');
@endphp

<div id="toast">✅ Скопировано!</div>

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

                        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="liquid-glass"
                               style="border-radius: 20px; padding: 20px; margin-bottom: 20px;">

                            <!-- Ссылка -->
                            <tr>
                                <td style="padding: 0 0 12px; border-bottom: 1px solid rgba(0,0,0,0.08);">
                                    <span style="font-size: 13.5px; color: #555; display: block; margin-bottom: 6px;">🔗 Ссылка</span>
                                    <div class="field-wrap">
                                        <div class="field-value">
                                            <a href="{{ $siteUrl }}">{{ $siteUrl }}</a>
                                        </div>
                                        <button class="copy-btn" onclick="copyText('{{ $siteUrl }}', this)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                            </svg>
                                            Копировать
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Логин -->
                            <tr>
                                <td style="padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.08);">
                                    <span style="font-size: 13.5px; color: #555; display: block; margin-bottom: 6px;">👤 Логин</span>
                                    <div class="field-wrap">
                                        <div class="field-value">admin</div>
                                        <button class="copy-btn" onclick="copyText('admin', this)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                            </svg>
                                            Копировать
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Пароль -->
                            <tr>
                                <td style="padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.08);">
                                    <span style="font-size: 13.5px; color: #555; display: block; margin-bottom: 6px;">🔑 Пароль</span>
                                    <div class="field-wrap">
                                        <div class="field-value" style="letter-spacing: 1px;">{{ $password }}</div>
                                        <button class="copy-btn" onclick="copyText('{{ $password }}', this)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                            </svg>
                                            Копировать
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- ID -->
                            <tr>
                                <td style="padding: 12px 0 0;">
                                    <span style="font-size: 13.5px; color: #555; display: block; margin-bottom: 6px;">🪪 ID</span>
                                    <div class="field-wrap">
                                        <div class="field-value">{{ $id }}</div>
                                        <button class="copy-btn" onclick="copyText('{{ $id }}', this)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                            </svg>
                                            Копировать
                                        </button>
                                    </div>
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

<script>
    let toastTimer;

    function copyText(text, btn) {
        const original = btn.innerHTML;

        function onSuccess() {
            btn.classList.add('copied');
            btn.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Скопировано`;

            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = original;
            }, 2000);

            const toast = document.getElementById('toast');
            clearTimeout(toastTimer);
            toast.classList.add('show');
            toastTimer = setTimeout(() => toast.classList.remove('show'), 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(onSuccess)
                .catch(() => fallbackCopy(text, onSuccess));
        } else {
            fallbackCopy(text, onSuccess);
        }
    }

    function fallbackCopy(text, callback) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            document.execCommand('copy');
            if (callback) callback();
        } catch (e) {}
        document.body.removeChild(ta);
    }
</script>

</body>
</html>
