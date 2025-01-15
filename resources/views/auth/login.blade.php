<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h1>Login</h1>
    </div>
    <form action="{{ route('auth.login') }}" method="POST" class="login-form">
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
</div>

<style>
    /* Global Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Arial', sans-serif;
        background-color: #f8f9fb;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .login-container {
        background-color: #0033c4;
        width: 400px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        padding: 30px;
        text-align: center;
    }

    .login-header h1 {
        color: #fdf9f9;
        margin-bottom: 20px;
    }

    .input-group {
        margin-bottom: 15px;
        text-align: left;
    }

    .input-group label {
        font-size: 14px;
        color: #fdf9f9;
    }

    .input-group input {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 14px;
    }

    .action-buttons {
        margin-top: 20px;
    }

    .submit-btn, .reset-btn {
        width: 100%;
        padding: 10px;
        font-size: 16px;
        color: #0a0a0a;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .submit-btn {
        background-color: #fdf9f9;
    }


</style>
</body>
</html>

