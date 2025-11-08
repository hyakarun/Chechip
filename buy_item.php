<?php
session_start();
if (!isset($_SESSION['player_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['item_id'])) {
    header('Location: login.php');
    exit();
}
require_once(__DIR__ . '/db_connect.php');
$player_id = $_SESSION['player_id'];
$item_id = (int)$_POST['item_id'];

try {
    $pdo = connectDb();
    $pdo->beginTransaction();

    // 1. アイテムの価格を取得
    $stmt_item = $pdo->prepare("SELECT * FROM items WHERE id = :item_id AND buy_price > 0");
    $stmt_item->bindValue(':item_id', $item_id, PDO::PARAM_INT);
    $stmt_item->execute();
    $item_template = $stmt_item->fetch();

    // 2. プレイヤーの所持金を取得
    $stmt_player = $pdo->prepare("SELECT gold FROM players WHERE player_id = :player_id FOR UPDATE"); // ロック
    $stmt_player->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_player->execute();
    $player_gold = $stmt_player->fetchColumn();

    // 3. 買えるかチェック
    if ($item_template && $player_gold >= $item_template['buy_price']) {
        // 4. お金を減らす
        $new_gold = $player_gold - $item_template['buy_price'];
        $stmt_update_gold = $pdo->prepare("UPDATE players SET gold = :gold WHERE player_id = :player_id");
        $stmt_update_gold->bindValue(':gold', $new_gold, PDO::PARAM_INT);
        $stmt_update_gold->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $stmt_update_gold->execute();
        
        // 5. アイテムをインベントリに追加
        // ★ お店で買うアイテムは、ランダムオプションなし（NULL）で追加
        $sql = "INSERT INTO player_inventory (player_id, item_id) VALUES (:player_id, :item_id)";
        $add_item_stmt = $pdo->prepare($sql);
        $add_item_stmt->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $add_item_stmt->bindValue(':item_id', $item_id, PDO::PARAM_INT);
        $add_item_stmt->execute();
        
        $pdo->commit();
    } else {
        // お金が足りない、またはアイテムが非売品
        $pdo->rollBack();
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    exit('データベースエラー: ' . $e->getMessage());
}

header('Location: shop.php');
exit();