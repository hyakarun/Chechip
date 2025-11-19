<?php
session_start();
if (!isset($_SESSION['player_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['item_id'])) {
    header('Location: login.php');
    exit();
}
require_once(__DIR__ . '/db_connect.php');

define('MAX_INVENTORY_SLOTS', 100);
define('MAX_STACK_QUANTITY', 99);

$player_id = $_SESSION['player_id'];
$item_id = (int)$_POST['item_id'];

try {
    $pdo = connectDb();
    $pdo->beginTransaction();

    // アイテム情報を取得 (not_stackableも取得)
    $stmt_item = $pdo->prepare("SELECT * FROM items WHERE id = :item_id AND buy_price > 0");
    $stmt_item->bindValue(':item_id', $item_id, PDO::PARAM_INT);
    $stmt_item->execute();
    $item_template = $stmt_item->fetch();

    if (!$item_template) {
        $pdo->rollBack();
        header('Location: shop.php');
        exit();
    }

    // 所持金チェック
    $stmt_player = $pdo->prepare("SELECT gold FROM players WHERE player_id = :player_id FOR UPDATE");
    $stmt_player->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_player->execute();
    $player_gold = $stmt_player->fetchColumn();

    if ($player_gold >= $item_template['buy_price']) {
        
        // 所持スロット数チェック
        $stmt_count = $pdo->prepare("SELECT COUNT(inventory_id) FROM player_inventory WHERE player_id = :player_id");
        $stmt_count->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $current_slots = $stmt_count->fetchColumn();

        // フラグ判定
        $is_equipment = ($item_template['type'] === 'equipment');
        $not_stackable = ($item_template['not_stackable'] == 1);

        // 1. 新しいスロットが必要なケース (装備品 または スタック不可 または スタック可だが空きがない)
        // まずはスタック可の場合の空きスロットを探す
        $existing_slot = false;
        if (!$is_equipment && !$not_stackable) {
             $stmt_find_stack = $pdo->prepare(
                "SELECT inventory_id, quantity FROM player_inventory 
                 WHERE player_id = :player_id AND item_id = :item_id AND quantity < :max_qty
                 ORDER BY inventory_id ASC LIMIT 1 FOR UPDATE"
            );
            $stmt_find_stack->bindValue(':player_id', $player_id, PDO::PARAM_INT);
            $stmt_find_stack->bindValue(':item_id', $item_id, PDO::PARAM_INT);
            $stmt_find_stack->bindValue(':max_qty', MAX_STACK_QUANTITY, PDO::PARAM_INT);
            $stmt_find_stack->execute();
            $existing_slot = $stmt_find_stack->fetch();
        }

        // スタック先がなく、かつスロットが満杯ならエラー
        if (!$existing_slot && $current_slots >= MAX_INVENTORY_SLOTS) {
            $pdo->rollBack();
            // 本来はエラーメッセージを表示すべきですが、簡易的にショップに戻します
            header('Location: shop.php'); 
            exit();
        }

        // 支払い
        $new_gold = $player_gold - $item_template['buy_price'];
        $stmt_update_gold = $pdo->prepare("UPDATE players SET gold = :gold WHERE player_id = :player_id");
        $stmt_update_gold->bindValue(':gold', $new_gold, PDO::PARAM_INT);
        $stmt_update_gold->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $stmt_update_gold->execute();

        // アイテム追加
        if ($existing_slot) {
            // スタックに追加
            $new_qty = $existing_slot['quantity'] + 1;
            $stmt_update = $pdo->prepare("UPDATE player_inventory SET quantity = :qty WHERE inventory_id = :id");
            $stmt_update->bindValue(':qty', $new_qty, PDO::PARAM_INT);
            $stmt_update->bindValue(':id', $existing_slot['inventory_id'], PDO::PARAM_INT);
            $stmt_update->execute();
        } else {
            // 新規スロット追加 (quantity=1)
            $sql = "INSERT INTO player_inventory (player_id, item_id, quantity) VALUES (:player_id, :item_id, 1)";
            $stmt_insert = $pdo->prepare($sql);
            $stmt_insert->bindValue(':player_id', $player_id, PDO::PARAM_INT);
            $stmt_insert->bindValue(':item_id', $item_id, PDO::PARAM_INT);
            $stmt_insert->execute();
        }
        
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    exit('データベースエラー: ' . $e->getMessage());
}

header('Location: shop.php');
exit();
?>