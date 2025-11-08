<?php
// admin_delete_drop.php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
require_once(__DIR__ . '/../db_connect.php');

$id = (int)$_POST['id'];

if ($id <= 0) {
    exit('IDが不正です。');
}

try {
    $pdo = connectDb();
    $stmt = $pdo->prepare("DELETE FROM monster_drops WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}

// 管理ツール画面に戻る
header('Location: admin_drops.php');
exit();
?>