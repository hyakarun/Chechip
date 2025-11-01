<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
require_once(__DIR__ . '/../db_connect.php');

$id = $_POST['id'];

try {
    $pdo = connectDb();
    $stmt = $pdo->prepare("DELETE FROM news WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}
header('Location: dashboard.php');