<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    <style>
        body {
            background-color: #000000;
            color: #eee;
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            /* ▼ ロゴとフォームを縦に並べるために追加 ▼ */
            flex-direction: column; 
        }
        .login-container {
            background-color: #333;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #555;
            width: 300px;
        }
        .logo {
            /* ▼ ロゴとフォームの間のマージン ▼ */
            margin-bottom: 25px; 
        }
        .logo img {
            max-width: 500px;
            height: auto;
            color: #eee;
        }
        h2 {
            color: #eee;
            /* ▼ ロゴを外に出したため、フォーム内のH2マージンを調整 ▼ */
            margin-top: 0; 
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            background-color: #555;
            color: #eee;
            border: 1px solid #666;
            border-radius: 4px;
            box-sizing: border-box; 
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #8af;
            color: #000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1em;
        }
        button:hover {
            background-color: #9bf;
        }
        a {
            color: #8af;
            display: block;
            margin-top: 15px;
            font-size: 0.9em;
        }
    </style>
    </head>
<body>

    <div class="logo">
        <img src="images/logo_wh_1st.png" alt="ロゴ">
    </div>

    <div class="login-container">
        
        <h2>ログイン</h2>
        
        <form action="handle_login.php" method="post">
            <div class="form-group">
                <label for="username">ユーザー名:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">ログイン</button>
        </form>
        
        <a href="register.php">新規登録はこちら</a>
    </div>

</body>
</html>