<?php
session_start();
require_once(__DIR__ . '/db_connect.php');

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

// リダイレクト先の初期設定
$redirect_url = 'Location: game.php';

try {
    $pdo = connectDb();
    
    // 現在のプレイヤー情報を取得 (HPも取得して満タンチェックを行う)
    $stmt = $pdo->prepare("SELECT level, gold, hp_max, hp FROM players WHERE player_id = :player_id");
    $stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $stmt->execute();
    $player = $stmt->fetch();

    if ($player) {
        $cost = $player['level'] * 1000;
        $is_fully_healed = $player['hp'] === $player['hp_max'];

        if ($player['gold'] < $cost) {
            // 料金が足りない場合
            $redirect_url = 'Location: game.php?inn_result=low_gold&cost=' . $cost;
            
        } elseif ($is_fully_healed) {
            // HPが満タンで回復の必要がない場合
            $redirect_url = 'Location: game.php?inn_result=full_hp';
            
        } else {
            // 料金が足りて、回復が必要な場合 (正常処理)
            $new_gold = $player['gold'] - $cost;
            $new_hp = $player['hp_max']; // HPを全回復

            // データベースを更新
            $update_stmt = $pdo->prepare("UPDATE players SET gold = :gold, hp = :hp WHERE player_id = :player_id");
            $update_stmt->bindValue(':gold', $new_gold, PDO::PARAM_INT);
            $update_stmt->bindValue(':hp', $new_hp, PDO::PARAM_INT);
            $update_stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
            $update_stmt->execute();
            
            $redirect_url = 'Location: game.php?inn_result=success';
        }
    }
} catch (PDOException $e) {
    // データベースエラーが発生した場合
    $redirect_url = 'Location: game.php?inn_result=db_error';
}

// 結果に応じてリダイレクト
header($redirect_url);
exit();
?>