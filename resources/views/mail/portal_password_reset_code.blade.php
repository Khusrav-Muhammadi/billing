<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля shamCRM Partners</title>
</head>
<body style="margin:0;padding:24px;background:#f7f8fa;font-family:Arial,sans-serif;color:#111826;">
<div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #dfe3ea;border-radius:12px;padding:28px;">
    <p style="margin:0 0 12px;font-size:16px;"><strong>Сброс пароля</strong></p>
    <p style="margin:0 0 16px;font-size:14px;line-height:1.5;">
        {{ $name ? $name . ', в' : 'В' }}ы запросили сброс пароля в партнёрском кабинете shamCRM. Введите этот код на странице сброса пароля:
    </p>
    <p style="margin:0 0 16px;font-size:32px;font-weight:bold;letter-spacing:8px;text-align:center;background:#eef4ff;border-radius:8px;padding:14px;">{{ $code }}</p>
    <p style="margin:0;font-size:13px;line-height:1.5;color:#637085;">
        Код действует {{ $ttl }} минут. Если вы не запрашивали сброс, просто проигнорируйте письмо: без кода пароль не изменится.
    </p>
</div>
</body>
</html>
