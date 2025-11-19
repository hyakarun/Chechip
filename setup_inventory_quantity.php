<?php
require_once(__DIR__ . '/db_connect.php');

echo "<h1>所持品数量システム移行ツール</h1>";

try {
    $pdo = connectDb();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. player_inventory テーブルに 'quantity' カラムを追加
    $columns = $pdo->query("SHOW COLUMNS FROM player_inventory LIKE 'quantity'")->fetchAll();
    if (empty($columns)) {
        // カラムがなければ追加し、既存のレコードの quantity を 1 に設定
        $pdo->exec("ALTER TABLE player_inventory ADD COLUMN quantity INT NOT NULL DEFAULT 1 COMMENT 'スタック数' AFTER item_id");
        echo "<p>✅ データベース構造を変更しました (<code>quantity</code> カラムを追加)。</p>";
    } else {
        echo "<p>ℹ️ データベース構造は既に変更済みです。</p>";
    }

    echo "<hr><h3>🎉 システム移行完了</h3>";
    echo "<p>続いて、関連プログラムファイルを修正してください。</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>エラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>