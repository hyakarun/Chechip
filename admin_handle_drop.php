<?php
// admin_handle_drop.php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
require_once(__DIR__ . '/../db_connect.php');

// --- ▼▼▼ (ここから変更) フォームから配列で受け取る ---
$monster_ids = $_POST['monster_id'] ?? []; // 配列
$item_ids = $_POST['item_id'] ?? [];       // 配列
// --- ▲▲▲ (変更ここまで) ---

// --- ▼▼▼ (変更) % から「重み」の整数に変更 ---
// (フォームから 10 や 50 などの「重み」をそのまま受け取る)
$drop_chance_weight = (int)$_POST['drop_chance']; 
// --- ▲▲▲ (変更ここまで) ---

// (★ 配列が空でないかチェック)
if (empty($monster_ids) || empty($item_ids) || $drop_chance_weight <= 0) { // (★ 変更)
    exit('モンスター、アイテム、ドロップの重みが正しく入力されていません。');
}

try {
    $pdo = connectDb();

    // --- ▼▼▼ (ここから変更) 一括登録処理 ---
    
    // (安全のためトランザクションを開始)
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO monster_drops (monster_id, item_id, drop_chance) 
            VALUES (:monster_id, :item_id, :drop_chance)";
    $stmt = $pdo->prepare($sql);
    
    // 選択された全モンスターでループ
    foreach ($monster_ids as $monster_id) {
        $monster_id_int = (int)$monster_id;
        
        // 選択された全アイテムでループ
        foreach ($item_ids as $item_id) {
            $item_id_int = (int)$item_id;
            
            // (SQLを実行)
            $stmt->bindValue(':monster_id', $monster_id_int, PDO::PARAM_INT);
            $stmt->bindValue(':item_id', $item_id_int, PDO::PARAM_INT);
            
            // --- ▼▼▼ (変更) 小数ではなく「重み(整数)」をそのまま保存 ---
            // (カラムの型がDECIMALやFLOATならSTR、INTならINT)
            $stmt->bindValue(':drop_chance', $drop_chance_weight, PDO::PARAM_STR); 
            // --- ▲▲▲ (変更ここまで) ---

            $stmt->execute();
        }
    }
    
    // (すべての処理が成功したら確定)
    $pdo->commit();
    // --- ▲▲▲ (変更ここまで) ---

} catch (PDOException $e) {
    $pdo->rollBack(); // エラーが起きたらすべて元に戻す
    exit('データベースエラー: ' . $e->getMessage());
}

// 管理ツール画面に戻る
header('Location: admin_drops.php');
exit();
?>