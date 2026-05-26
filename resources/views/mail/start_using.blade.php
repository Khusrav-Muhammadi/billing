<style>
    .w{max-width:560px;margin:0 auto;font-family:Arial,sans-serif;border-radius:16px;overflow:hidden;background:#2b3180}
    .hero{background:#2b3180;padding:44px 44px 36px;position:relative;overflow:hidden}
    .hex-bg{position:absolute;top:-60px;right:-60px;opacity:0.1}
    .hex-bl{position:absolute;bottom:-80px;left:-60px;opacity:0.07}
    .brand{text-align:center;margin-bottom:36px}
    .brand-name{color:rgba(255,255,255,0.95);font-size:13px;letter-spacing:0.18em;font-weight:700}
    .brand-name span{background:#4d55d4;color:#d0d3ff;font-size:11px;letter-spacing:0.1em;padding:2px 7px;border-radius:4px;margin-left:3px;vertical-align:middle}
    .hero-tag{display:inline-block;border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.6);font-size:11px;letter-spacing:0.1em;padding:4px 12px;border-radius:20px;margin-bottom:16px}
    .hero h1{color:#fff;font-size:25px;font-weight:700;margin:0 0 10px;line-height:1.3;text-align:center}
    .hero-sub{color:rgba(255,255,255,0.5);font-size:13px;text-align:center;margin:0}
    .stripe{height:3px;background:#5b64f0}
    .body{background:#fff;padding:32px 40px}
    .feats{background:#f5f6ff;border-radius:10px;padding:18px 20px;margin:20px 0}
    .feat{display:flex;align-items:center;gap:13px;padding:8px 0}
    .feat+.feat{border-top:1px solid #eaecff}
    .ficon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:#eef0ff}
    .cta{display:block;background:#2b3180;color:#fff;text-decoration:none;border-radius:10px;padding:15px;font-size:15px;font-weight:700;text-align:center;margin:24px 0 0;letter-spacing:0.02em}
    .foot{background:#1f256b;padding:18px 40px;display:flex;align-items:center;justify-content:space-between}
</style>

<div class="w">

    <div class="hero">
        <svg class="hex-bg" width="280" height="280" viewBox="0 0 280 280">
            <polygon points="140,14 252,77 252,203 140,266 28,203 28,77" fill="none" stroke="#fff" stroke-width="2"/>
            <polygon points="140,42 228,91 228,189 140,238 52,189 52,91" fill="none" stroke="#fff" stroke-width="1.2"/>
            <polygon points="140,70 204,105 204,175 140,210 76,175 76,105" fill="none" stroke="#fff" stroke-width="0.7"/>
        </svg>
        <svg class="hex-bl" width="200" height="200" viewBox="0 0 200 200">
            <polygon points="100,10 180,55 180,145 100,190 20,145 20,55" fill="none" stroke="#fff" stroke-width="1.5"/>
            <polygon points="100,35 155,67 155,133 100,165 45,133 45,67" fill="none" stroke="#fff" stroke-width="0.8"/>
        </svg>

        <div class="brand">
            <svg width="52" height="52" viewBox="0 0 52 52" fill="none" style="display:block;margin:0 auto 12px">
                <circle cx="26" cy="26" r="26" fill="#3c44a8"/>
                <g transform="translate(10,10)">
                    <path d="M16 4 C20 4 24 7 24 11 C24 14 22 16 19 17 C22 18 25 21 25 25 C25 29 21 32 16 32 C11 32 7 29 7 25 C7 21 10 18 13 17 C10 16 8 14 8 11 C8 7 12 4 16 4Z" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                    <circle cx="16" cy="11" r="3" fill="white" opacity="0.8"/>
                    <circle cx="16" cy="25" r="3" fill="white" opacity="0.8"/>
                </g>
            </svg>
            <div class="brand-name">SHAM<span>CRM</span></div>
        </div>

        <div style="text-align:center">
            <div class="hero-tag">ДОБРО ПОЖАЛОВАТЬ</div>
            <h1>Вы уже начали<br>пользоваться shamCRM</h1>
            <p class="hero-sub">Сейчас самое главное — начать использовать систему в работе</p>
        </div>
    </div>

    <div class="stripe"></div>

    <div class="body">
        <p style="color:#1a1a2e;font-size:15px;margin:0 0 6px">Здравствуйте!</p>
        <p style="color:#6b7280;font-size:14px;line-height:1.75;margin:0 0 4px">Не просто смотрите систему — начните использовать её прямо сейчас. Вот что уже доступно вам:</p>

        <div class="feats">
            <p style="color:#9ca3af;font-size:11px;letter-spacing:0.08em;margin:0 0 10px;font-weight:700">УЖЕ СЕЙЧАС ВЫ МОЖЕТЕ</p>

            <div class="feat">
                <div class="ficon">
                    <i class="ti ti-inbox" style="color:#4d55d4;font-size:16px" aria-hidden="true"></i>
                </div>
                <div>
                    <p style="font-size:14px;color:#111827;margin:0;font-weight:700">Собирать все заявки в одном месте</p>
                    <p style="font-size:12px;color:#9ca3af;margin:2px 0 0">Ни одна заявка не потеряется</p>
                </div>
            </div>

            <div class="feat">
                <div class="ficon">
                    <i class="ti ti-brand-telegram" style="color:#4d55d4;font-size:16px" aria-hidden="true"></i>
                </div>
                <div>
                    <p style="font-size:14px;color:#111827;margin:0;font-weight:700">Подключить Instagram, Telegram и WhatsApp</p>
                    <p style="font-size:12px;color:#9ca3af;margin:2px 0 0">Все мессенджеры в одном окне</p>
                </div>
            </div>

            <div class="feat">
                <div class="ficon">
                    <i class="ti ti-users" style="color:#4d55d4;font-size:16px" aria-hidden="true"></i>
                </div>
                <div>
                    <p style="font-size:14px;color:#111827;margin:0;font-weight:700">Контролировать менеджеров</p>
                    <p style="font-size:12px;color:#9ca3af;margin:2px 0 0">Видеть эффективность каждого сотрудника</p>
                </div>
            </div>

            <div class="feat">
                <div class="ficon">
                    <i class="ti ti-phone-call" style="color:#4d55d4;font-size:16px" aria-hidden="true"></i>
                </div>
                <div>
                    <p style="font-size:14px;color:#111827;margin:0;font-weight:700">Фиксировать звонки и продажи</p>
                    <p style="font-size:12px;color:#9ca3af;margin:2px 0 0">Полная история по каждому клиенту</p>
                </div>
            </div>

            <div class="feat">
                <div class="ficon">
                    <i class="ti ti-chart-bar" style="color:#4d55d4;font-size:16px" aria-hidden="true"></i>
                </div>
                <div>
                    <p style="font-size:14px;color:#111827;margin:0;font-weight:700">Видеть клиентов и сделки</p>
                    <p style="font-size:12px;color:#9ca3af;margin:2px 0 0">Полная картина продаж в реальном времени</p>
                </div>
            </div>
        </div>

        <div style="background:#f5f6ff;border-left:3px solid #5b64f0;border-radius:0 8px 8px 0;padding:13px 16px 13px 18px;margin:20px 0">
            <p style="color:#2b3180;font-size:14px;line-height:1.7;margin:0;font-style:italic">Не упустите возможность навести порядок в продажах уже сейчас. Если нужна помощь — мы всегда на связи.</p>
        </div>

{{--        <a class="cta" href="https://shamcrm.com">Начать работу в shamCRM →</a>--}}
    </div>

    <div class="foot">
        <span style="color:rgba(255,255,255,0.7);font-size:13px;font-weight:700;letter-spacing:0.05em">SHAMCRM</span>
        <div style="display:flex;gap:20px;align-items:center">
            <a href="https://shamcrm.com" style="color:rgba(255,255,255,0.5);font-size:12px;text-decoration:none">shamcrm.com</a>
            <span style="color:rgba(255,255,255,0.5);font-size:12px">+998-55-588-81-00</span>
        </div>
    </div>

</div>
