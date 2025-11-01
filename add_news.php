<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
require_once(__DIR__ . '/../db_connect.php');

$title = $_POST['title'];
$link_url = $_POST['link_url'];
$link_text = $_POST['link_text'];

try {
    $pdo = connectDb();
    $stmt = $pdo->prepare("INSERT INTO news (title, link_url, link_text) VALUES (:title, :link_url, :link_text)");
    $stmt->bindValue(':title', $title, PDO::PARAM_STR);
    $stmt->bindValue(':link_url', $link_url, PDO::PARAM_STR);
    $stmt->bindValue(':link_text', $link_text, PDO::PARAM_STR);
    $stmt->execute();
} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}
header('Location: dashboard.php');