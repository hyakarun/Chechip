<?php
require_once(__DIR__ . '/db_connect.php');

echo "<h1>スタック不可フラグ追加ツール</h1>";

try {
    $pdo = connectDb();

    // 1. items テーブルに 'not_stackable' カラムを追加
    // 0: スタック可 (デフォルト), 1: スタック不可
    $columns = $pdo->query("SHOW COLUMNS FROM items LIKE 'not_stackable'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE items ADD COLUMN not_stackable TINYINT NOT NULL DEFAULT 0 COMMENT '0:可, 1:不可' AFTER buy_price");
        echo "<p>✅ itemsテーブルに `not_stackable` カラムを追加しました。</p>";
    } else {
        echo "<p>ℹ️ `not_stackable` カラムは既に追加済みです。</p>";
    }

    echo "<hr><h3>設定完了</h3>";
    echo "<p>データベースの準備が整いました。次にプログラムを更新してください。</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>