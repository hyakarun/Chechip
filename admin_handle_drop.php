<?php
// admin_handle_drop.php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
require_once(__DIR__ . '/../db_connect.php');

$monster_id = (int)$_POST['monster_id'];
$item_id = (int)$_POST['item_id'];
// フォームからは 20 (%) で受け取り、DBには 0.20 (小数) で保存
$drop_chance = (float)$_POST['drop_chance'] / 100.0; 

if ($monster_id <= 0 || $item_id <= 0 || $drop_chance <= 0) {
    exit('入力値が不正です。');
}

try {
    $pdo = connectDb();
    $sql = "INSERT INTO monster_drops (monster_id, item_id, drop_chance) VALUES (:monster_id, :item_id, :drop_chance)";
    $stmt = $pdo->prepare($sql);
    
    $stmt->bindValue(':monster_id', $monster_id, PDO::PARAM_INT);
    $stmt->bindValue(':item_id', $item_id, PDO::PARAM_INT);
    $stmt->bindValue(':drop_chance', $drop_chance, PDO::PARAM_STR); // DECIMALはSTR型で
    
    $stmt->execute();

} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}

// 管理ツール画面に戻る
header('Location: admin_drops.php');
exit();
?>