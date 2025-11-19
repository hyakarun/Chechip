<?php
require_once(__DIR__ . '/db_connect.php');

echo "<h1>Cheychip 'hand' システム移行ツール</h1>";

try {
    $pdo = connectDb();

    // 1. player_inventory テーブルに 'equipped_slot' カラムを追加
    // (まだ存在しない場合のみ追加)
    $columns = $pdo->query("SHOW COLUMNS FROM player_inventory LIKE 'equipped_slot'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE player_inventory ADD COLUMN equipped_slot VARCHAR(50) DEFAULT NULL AFTER is_equipped");
        echo "<p>✅ データベース構造を変更しました (`equipped_slot` カラムを追加)。</p>";
    } else {
        echo "<p>ℹ️ データベース構造は既に変更済みです。</p>";
    }

    // 2. 既存の装備中アイテムのデータを移行
    // (今の items テーブルの情報を元に、equipped_slot に 'right_hand' や 'left_hand' を書き込む)
    $sql_migrate = "
        UPDATE player_inventory pi
        JOIN items i ON pi.item_id = i.id
        SET pi.equipped_slot = i.equip_slot
        WHERE pi.is_equipped = 1 
        AND pi.equipped_slot IS NULL
        AND (i.equip_slot = 'right_hand' OR i.equip_slot = 'left_hand' OR i.equip_slot = 'hand')
    ";
    $stmt_migrate = $pdo->prepare($sql_migrate);
    $stmt_migrate->execute();
    $count_migrate = $stmt_migrate->rowCount();
    if ($count_migrate > 0) {
        echo "<p>✅ 既存の装備データ $count_migrate 件を新しい形式に移行しました。</p>";
    }

    // 3. items テーブルの 'right_hand', 'left_hand' をすべて 'hand' に統一
    $sql_update_items = "
        UPDATE items 
        SET equip_slot = 'hand' 
        WHERE equip_slot IN ('right_hand', 'left_hand')
    ";
    $stmt_update = $pdo->prepare($sql_update_items);
    $stmt_update->execute();
    $count_update = $stmt_update->rowCount();
    
    if ($count_update > 0) {
        echo "<p>✅ アイテムデータ $count_update 件の種別を 'hand' に統一しました。</p>";
    } else {
        echo "<p>ℹ️ アイテムデータは既に 'hand' に統一されているか、対象データがありません。</p>";
    }

    echo "<hr><h3>🎉 システム移行完了</h3>";
    echo "<p>続いて、game.php, equipment.php, equip_item.php などのプログラムファイルを修正してください。</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>エラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>