<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
require_once(__DIR__ . '/../db_connect.php');

// --- ▼▼▼ フォームから送られてきたデータをすべて受け取る ▼▼▼ ---
$name = $_POST['name'];
$level = (int)$_POST['level'];
$exp = (int)$_POST['exp'];
$hp = (int)$_POST['hp'];
$atk = (int)$_POST['atk'];
$def = (int)$_POST['def'];
$strength = (int)$_POST['strength'];
$vitality = (int)$_POST['vitality'];
$intelligence = (int)$_POST['intelligence'];
$speed = (int)$_POST['speed'];
$luck = (int)$_POST['luck'];
$charisma = (int)$_POST['charisma'];
$gold = (int)$_POST['gold'];
$image = $_POST['image']; // 画像ファイル名
// --- ▲▲▲ ここまで ▲▲▲ ---

try {
    $pdo = connectDb();
    
    // --- ▼▼▼ INSERT文を monsters テーブル用に書き換える ▼▼▼ ---
    $sql = "INSERT INTO monsters 
                (name, level, exp, hp, atk, def, strength, vitality, intelligence, speed, luck, charisma, gold, image) 
            VALUES 
                (:name, :level, :exp, :hp, :atk, :def, :strength, :vitality, :intelligence, :speed, :luck, :charisma, :gold, :image)";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':level', $level, PDO::PARAM_INT);
    $stmt->bindValue(':exp', $exp, PDO::PARAM_INT);
    $stmt->bindValue(':hp', $hp, PDO::PARAM_INT);
    $stmt->bindValue(':atk', $atk, PDO::PARAM_INT);
    $stmt->bindValue(':def', $def, PDO::PARAM_INT);
    $stmt->bindValue(':strength', $strength, PDO::PARAM_INT);
    $stmt->bindValue(':vitality', $vitality, PDO::PARAM_INT);
    $stmt->bindValue(':intelligence', $intelligence, PDO::PARAM_INT);
    $stmt->bindValue(':speed', $speed, PDO::PARAM_INT);
    $stmt->bindValue(':luck', $luck, PDO::PARAM_INT);
    $stmt->bindValue(':charisma', $charisma, PDO::PARAM_INT);
    $stmt->bindValue(':gold', $gold, PDO::PARAM_INT);
    $stmt->bindValue(':image', $image, PDO::PARAM_STR);
    
    $stmt->execute();
    // --- ▲▲▲ ここまで ▲▲▲ ---

} catch (PDOException $e) {
    exit('データベースエラー: ' :. $e->getMessage());
}

// ★ 完了したらモンスター管理ページに戻る
header('Location: admin_monsters.php');