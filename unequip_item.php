<?php
session_start();
if (!isset($_SESSION['player_id']) || !isset($_GET['inventory_id'])) {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');

$player_id = $_SESSION['player_id'];
$inventory_id_to_unequip = (int)$_GET['inventory_id'];

try {
    $pdo = connectDb();
    
    // 装備を外し、equipped_slot も NULL にする
    $stmt = $pdo->prepare(
        "UPDATE player_inventory
         SET is_equipped = 0, equipped_slot = NULL
         WHERE player_id = :player_id AND inventory_id = :inventory_id"
    );
    $stmt->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt->bindValue(':inventory_id', $inventory_id_to_unequip, PDO::PARAM_INT);
    $stmt->execute();
    
} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}

header('Location: equipment.php');
exit();
?>