<?php
session_start();
require_once(__DIR__ . '/db_connect.php');

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = connectDb();
    
    // 現在のプレイヤー情報を取得
    $stmt = $pdo->prepare("SELECT level, gold, hp_max FROM players WHERE player_id = :player_id");
    $stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $stmt->execute();
    $player = $stmt->fetch();

    if ($player) {
        // 料金を計算 (サーバー側で再計算することが重要)
        $cost = $player['level'] * 1000;

        // お金が足りるかチェック
        if ($player['gold'] >= $cost) {
            // 新しい所持金とHPを計算
            $new_gold = $player['gold'] - $cost;
            $new_hp = $player['hp_max']; // HPを全回復

            // データベースを更新
            $update_stmt = $pdo->prepare("UPDATE players SET gold = :gold, hp = :hp WHERE player_id = :player_id");
            $update_stmt->bindValue(':gold', $new_gold, PDO::PARAM_INT);
            $update_stmt->bindValue(':hp', $new_hp, PDO::PARAM_INT);
            $update_stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
            $update_stmt->execute();

        } else {
            // お金が足りない場合 (今回は何もしないが、将来的にはメッセージ表示など)
            // echo "お金が足りません。";
        }
    }
} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}

// 処理が終わったら、ホーム画面に戻る
header('Location: game.php');
exit();