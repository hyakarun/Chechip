<?php
session_start();
if (!isset($_SESSION['player_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');

$player_id = $_SESSION['player_id'];
$inventory_id_to_equip = (int)$_POST['inventory_id'];
$slot_to_equip = $_POST['slot']; // 例: 'right_hand' または 'left_hand'

try {
    $pdo = connectDb();
    $pdo->beginTransaction();
    
    // 1. 指定されたスロットに既に装備されているアイテムがあれば外す
    $stmt_unequip = $pdo->prepare(
        "UPDATE player_inventory
         SET is_equipped = 0, equipped_slot = NULL
         WHERE player_id = :player_id AND equipped_slot = :slot"
    );
    $stmt_unequip->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_unequip->bindValue(':slot', $slot_to_equip, PDO::PARAM_STR);
    $stmt_unequip->execute();
    
    // 2. 新しいアイテムを装備する
    // (equipped_slot に 'right_hand' などを書き込む)
    $stmt_equip = $pdo->prepare(
        "UPDATE player_inventory
         SET is_equipped = 1, equipped_slot = :slot
         WHERE player_id = :player_id AND inventory_id = :inventory_id AND is_equipped = 0"
    );
    $stmt_equip->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_equip->bindValue(':inventory_id', $inventory_id_to_equip, PDO::PARAM_INT);
    $stmt_equip->bindValue(':slot', $slot_to_equip, PDO::PARAM_STR);
    $stmt_equip->execute();
    
    $pdo->commit();
    
} catch (PDOException $e) {
    $pdo->rollBack();
    exit('データベースエラー: ' . $e->getMessage());
}

header('Location: equipment.php');
exit();
?>