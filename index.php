<?php
session_start();
// ▼▼▼ あなただけの秘密のパスワードを設定してください ▼▼▼
define('ADMIN_PASSWORD', 'いつもの');
// ▲▲▲ ここまで ▲▲▲

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
        header('Location: dashboard.php');
        exit();
    } else {
        $error_message = 'パスワードが違います。';
    }
}

if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理画面ログイン</title>
    </head>
<body>
    <h1>管理画面ログイン</h1>
    <form method="post">
        <input type="password" name="password" placeholder="パスワード">
        <button type="submit">ログイン</button>
    </form>
    <?php if ($error_message): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>
</body>
</html>
