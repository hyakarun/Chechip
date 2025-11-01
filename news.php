<?php
// ログイン状態を確認するための定型文
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>お知らせ</title>
    <style>
        body { background-color: #333; color: #eee; font-family: sans-serif; }
        .container { max-width: 800px; margin: 40px auto; padding: 20px; background-color: #282828; border: 1px solid #555; }
        h1 { border-bottom: 1px solid #555; padding-bottom: 10px; }
        .news-item a { color: #8af; font-size: 1.2em; }
        .back-link { display: inline-block; margin-top: 30px; color: #eee; }
    </style>
</head>
<body>
    <div class="container">
        <h1>お知らせ</h1>

        <div class="news-item">
            <p>開発ブログを公開中です！</p>
            <a href="https://note.com/あなたのID" target="_blank" rel="noopener noreferrer">
                最新情報はこちら (note)
            </a>
            </div>

        <hr>

        <a href="game.php" class="back-link">ゲームに戻る</a>
    </div>
</body>
</html>