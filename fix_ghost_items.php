<?php
require_once(__DIR__ . '/db_connect.php');

echo "<h1>幽霊データ（存在しないアイテム）削除ツール</h1>";

try {
    $pdo = connectDb();

    // 1. 幽霊データの数を数える
    // (itemsテーブルにIDが存在しない player_inventory のデータ)
    $sql_count = "
        SELECT COUNT(*) 
        FROM player_inventory pi
        LEFT JOIN items i ON pi.item_id = i.id
        WHERE i.id IS NULL
    ";
    $stmt = $pdo->query($sql_count);
    $ghost_count = $stmt->fetchColumn();

    echo "<p>現在の幽霊データ数: <strong>$ghost_count 件</strong></p>";

    if ($ghost_count > 0) {
        // 2. 削除実行
        $sql_delete = "
            DELETE pi FROM player_inventory pi
            LEFT JOIN items i ON pi.item_id = i.id
            WHERE i.id IS NULL
        ";
        $count = $pdo->exec($sql_delete);
        echo "<p style='color:red;'>🗑️ $count 件の幽霊データを削除しました。</p>";
        echo "<p style='color:green;'>データベースがクリーンになりました！</p>";
    } else {
        echo "<p style='color:green;'>正常です。幽霊データはありません。</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><a href='inventory.php'>所持品画面に戻る</a>";
?>