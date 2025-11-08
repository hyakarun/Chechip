<?php
session_start();
if (!isset($_SESSION['player_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');

$player_id = $_SESSION['player_id'];
$inventory_id_to_equip = (int)$_POST['inventory_id'];
$slot_to_equip = $_POST['slot'];

try {
    $pdo = connectDb();
    
    // トランザクション開始 (安全な処理のため)
    $pdo->beginTransaction();
    
    // 1. まず、そのスロットに「既に装備しているアイテム」を探して外す
    $stmt_unequip = $pdo->prepare(
        "UPDATE player_inventory pi
         JOIN items i ON pi.item_id = i.id
         SET pi.is_equipped = 0
         WHERE pi.player_id = :player_id AND i.equip_slot = :slot"
    );
    $stmt_unequip->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_unequip->bindValue(':slot', $slot_to_equip, PDO::PARAM_STR);
    $stmt_unequip->execute();
    
    // 2. 次に、新しく選んだアイテムを装備する
    $stmt_equip = $pdo->prepare(
        "UPDATE player_inventory
         SET is_equipped = 1
         WHERE player_id = :player_id AND inventory_id = :inventory_id AND is_equipped = 0"
    );
    $stmt_equip->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_equip->bindValue(':inventory_id', $inventory_id_to_equip, PDO::PARAM_INT);
    $stmt_equip->execute();
    
    // 処理を確定
    $pdo->commit();
    
} catch (PDOException $e) {
    $pdo->rollBack(); // エラーが起きたら元に戻す
    exit('データベースエラー: ' . $e->getMessage());
}

// 装備画面に戻る
header('Location: equipment.php');
exit();