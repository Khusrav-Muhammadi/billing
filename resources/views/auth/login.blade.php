<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Биллинг – Вход</title>
    <!-- Подключение Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Сброс стилей */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            text-align: center;
        }

        .login-header {
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 28px;
            color: #1e3c72;
            margin-bottom: 10px;
        }

        .login-header p {
            font-size: 14px;
            color: #555;
        }

        form {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
            display: block;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .input-group input:focus {
            border-color: #1e3c72;
            outline: none;
        }

        .action-buttons {
            margin-top: 10px;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background-color: #1e3c72;
            color: #fff;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .submit-btn:hover {
            background-color: #16325c;
        }

        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            text-align: center;
        }

        .footer-text {
            font-size: 12px;
            color: #777;
            margin-top: 20px;
            text-align: center;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h1>Биллинг</h1>
        <p>Вход в систему биллинга</p>
    </div>
    <form action="{{ route('auth.login') }}" method="POST">
        @csrf
        <div class="input-group">
            <label for="username">Логин</label>
            <input type="text" id="username" name="login" required>
        </div>
        <div class="input-group">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="action-buttons">
            <button type="submit" class="submit-btn">Войти</button>
        </div>
    </form>
    @if (session('error'))
        <p class="error-message" style="margin-top: 15px;">{{ session('error') }}</p>
    @endif
    <div class="footer-text">
        <p>© 2025 Биллинг. Все права защищены.</p>
    </div>
</div>
</body>
</html>
