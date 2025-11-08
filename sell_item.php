<?php
session_start();
if (!isset($_SESSION['player_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['inventory_id'])) {
    header('Location: login.php');
    exit();
}
require_once(__DIR__ . '/db_connect.php');
$player_id = $_SESSION['player_id'];
$inventory_id = (int)$_POST['inventory_id'];

try {
    $pdo = connectDb();
    $pdo->beginTransaction();

    // 1. 売ろうとしているアイテムが本当に存在するか、買値はいくらかを取得
    $stmt_item = $pdo->prepare(
        "SELECT i.buy_price FROM player_inventory pi
         JOIN items i ON pi.item_id = i.id
         WHERE pi.player_id = :player_id AND pi.inventory_id = :inventory_id AND pi.is_equipped = 0"
    );
    $stmt_item->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_item->bindValue(':inventory_id', $inventory_id, PDO::PARAM_INT);
    $stmt_item->execute();
    $item_info = $stmt_item->fetch();

    // 2. アイテムが存在し、売却可能（買値が設定されている）か
    if ($item_info) {
        // 3. アイテムをインベントリから削除
        $stmt_delete = $pdo->prepare("DELETE FROM player_inventory WHERE inventory_id = :inventory_id AND player_id = :player_id");
        $stmt_delete->bindValue(':inventory_id', $inventory_id, PDO::PARAM_INT);
        $stmt_delete->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        // 4. 売却額（買値の半額・切り捨て）を計算
        $sell_price = floor($item_info['buy_price'] / 2);
        
        // 5. プレイヤーの所持金を増やす
        $stmt_update_gold = $pdo->prepare("UPDATE players SET gold = gold + :sell_price WHERE player_id = :player_id");
        $stmt_update_gold->bindValue(':sell_price', $sell_price, PDO::PARAM_INT);
        $stmt_update_gold->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $stmt_update_gold->execute();
        
        $pdo->commit();
    } else {
        // 装備中のアイテム、または存在しないアイテムを売ろうとした
        $pdo->rollBack();
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    exit('データベースエラー: ' . $e->getMessage());
}

header('Location: shop.php');
exit();