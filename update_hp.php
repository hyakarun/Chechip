<?php
session_start();
require_once(__DIR__ . '/db_connect.php');

// ログイン中のプレイヤーからの正しいリクエストかチェック
if (!isset($_SESSION['player_id']) || !isset($_POST['hp'])) {
    http_response_code(400); // 不正なリクエスト
    exit();
}

$new_hp = (int)$_POST['hp'];
$player_id = $_SESSION['player_id'];

try {
    $pdo = connectDb();

    // プレイヤーの最大HPを取得して、不正な値でないかチェック
    $stmt = $pdo->prepare("SELECT hp_max FROM players WHERE player_id = :player_id");
    $stmt->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt->execute();
    $player = $stmt->fetch();

    // 送られてきたHPが最大HP以下の場合のみ、DBを更新
    if ($player && $new_hp <= $player['hp_max']) {
        $update_stmt = $pdo->prepare("UPDATE players SET hp = :hp WHERE player_id = :player_id");
        $update_stmt->bindValue(':hp', $new_hp, PDO::PARAM_INT);
        $update_stmt->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $update_stmt->execute();
    }

} catch (PDOException $e) {
    http_response_code(500); // サーバーエラー
    exit('データベースエラー');
}