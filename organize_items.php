<?php
session_start();
require_once(__DIR__ . '/db_connect.php');

// 定数: 1スタックの最大数
define('MAX_STACK', 99);

if (!isset($_SESSION['player_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inventory.php');
    exit();
}

$player_id = $_SESSION['player_id'];

try {
    $pdo = connectDb();
    $pdo->beginTransaction();

    // 1. 整理対象となるアイテムIDリストを取得
    // 条件: 装備品ではない(type!='equipment') かつ スタック可能(not_stackable=0)
    $stmt_targets = $pdo->prepare(
        "SELECT DISTINCT pi.item_id
         FROM player_inventory pi
         JOIN items i ON pi.item_id = i.id
         WHERE pi.player_id = :player_id 
           AND i.type != 'equipment' 
           AND i.not_stackable = 0"
    );
    $stmt_targets->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_targets->execute();
    $target_item_ids = $stmt_targets->fetchAll(PDO::FETCH_COLUMN);

    foreach ($target_item_ids as $item_id) {
        // 2. そのアイテムの総所持数を計算
        $stmt_sum = $pdo->prepare(
            "SELECT SUM(quantity) FROM player_inventory 
             WHERE player_id = :player_id AND item_id = :item_id"
        );
        $stmt_sum->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $stmt_sum->bindValue(':item_id', $item_id, PDO::PARAM_INT);
        $stmt_sum->execute();
        $total_quantity = (int)$stmt_sum->fetchColumn();

        if ($total_quantity > 0) {
            // 3. 既存データを全て削除
            $stmt_del = $pdo->prepare(
                "DELETE FROM player_inventory 
                 WHERE player_id = :player_id AND item_id = :item_id"
            );
            $stmt_del->bindValue(':player_id', $player_id, PDO::PARAM_INT);
            $stmt_del->bindValue(':item_id', $item_id, PDO::PARAM_INT);
            $stmt_del->execute();

            // 4. 99個ごとの塊と余りに分けて再登録
            $full_stacks = floor($total_quantity / MAX_STACK);
            $remainder = $total_quantity % MAX_STACK;

            $stmt_ins = $pdo->prepare(
                "INSERT INTO player_inventory (player_id, item_id, quantity) 
                 VALUES (:player_id, :item_id, :quantity)"
            );

            // 99個のスタックを作成
            for ($i = 0; $i < $full_stacks; $i++) {
                $stmt_ins->bindValue(':player_id', $player_id, PDO::PARAM_INT);
                $stmt_ins->bindValue(':item_id', $item_id, PDO::PARAM_INT);
                $stmt_ins->bindValue(':quantity', MAX_STACK, PDO::PARAM_INT);
                $stmt_ins->execute();
            }

            // 余りのスタックを作成
            if ($remainder > 0) {
                $stmt_ins->bindValue(':player_id', $player_id, PDO::PARAM_INT);
                $stmt_ins->bindValue(':item_id', $item_id, PDO::PARAM_INT);
                $stmt_ins->bindValue(':quantity', $remainder, PDO::PARAM_INT);
                $stmt_ins->execute();
            }
        }
    }

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    exit('データベースエラー: ' . $e->getMessage());
}

header('Location: inventory.php');
exit();
?>