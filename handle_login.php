<?php
session_start(); // セッションを開始
require_once(__DIR__ . '/db_connect.php'); // DB接続

// フォームからデータを受け取る
$name = $_POST['name'];
$password = $_POST['password'];

try {
    $pdo = connectDb();
    // 入力された名前でプレイヤーを検索
    $stmt = $pdo->prepare("SELECT player_id, name, password_hash FROM players WHERE name = :name");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->execute();
    $player = $stmt->fetch();

    // プレイヤーが存在し、かつパスワードが一致するか検証
    if ($player && password_verify($password, $player['password_hash'])) {
        // ログイン成功
        // セッションにプレイヤー情報を保存
        $_SESSION['player_id'] = $player['player_id'];
        $_SESSION['player_name'] = $player['name'];

        // ゲームメイン画面へリダイレクト
        header('Location: game.php');
        exit();
    } else {
        // ログイン失敗
        exit('名前またはパスワードが間違っています。');
    }
} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}