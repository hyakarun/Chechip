<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ちぇいぶちぷたーのたまご - ログイン</title>
    <style>
        body { font-family: sans-serif; text-align: center; }
        .form-container { margin: 50px auto; padding: 20px; border: 1px solid #ccc; width: 300px; }
        input { width: 90%; padding: 8px; margin-top: 10px; }
        button { padding: 10px 20px; margin-top: 20px; }
        .links { margin-top: 15px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>ログイン</h2>
        <form action="handle_login.php" method="post">
            <div>
                <label for="name">名前:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div>
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">ログイン</button>
        </form>
        <div class="links">
            <a href="register.php">新規登録はこちら</a>
        </div>
    </div>
</body>
</html>