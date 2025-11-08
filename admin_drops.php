<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: index.php');
    exit();
}
require_once(__DIR__ . '/../db_connect.php');
try {
    $pdo = connectDb();
    
    // フォームのプルダウン用
    $monsters = $pdo->query("SELECT id, name, level FROM monsters ORDER BY level, id")->fetchAll();
    // (items テーブルが存在することが前提です)
    $items = $pdo->query("SELECT id, name FROM items ORDER BY id")->fetchAll();
    
    // 現在のドロップ設定一覧を取得
    $drops_stmt = $pdo->query(
        "SELECT 
            md.id, 
            m.name AS monster_name, 
            i.name AS item_name, 
            md.drop_chance
         FROM monster_drops md
         JOIN monsters m ON md.monster_id = m.id
         JOIN items i ON md.item_id = i.id
         ORDER BY m.id, md.id"
    );
    $current_drops = $drops_stmt->fetchAll();

} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>アイテムドロップ管理</title>
    <style>
        body { font-family: sans-serif; }
        .container { max-width: 900px; margin: 20px auto; }
        form { margin-bottom: 20px; padding: 15px; background-color: #f4f4f4; border: 1px solid #ccc; }
        select, input[type="number"] { padding: 5px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        .delete-form { display: inline; }
        .delete-form button { padding: 3px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>アイテムドロップ管理</h1>
        <p>
            <a href="dashboard.php">ダッシュボード</a> | 
            <a href="admin_monsters.php">モンスター管理</a> | 
            <a href="admin_generate_exp.php">経験値テーブル管理</a> | 
            <a href="logout.php">ログアウト</a>
        </p>
        <hr>

        <h2>新しいドロップを設定</h2>
        <form action="admin_handle_drop.php" method="post">
            <p>
                <label for="monster_id">モンスター:</label>
                <select id="monster_id" name="monster_id" required>
                    <option value="" disabled selected>-- モンスターを選択 --</option>
                    <?php foreach ($monsters as $monster): ?>
                        <option value="<?php echo $monster['id']; ?>">
                            (Lv<?php echo $monster['level']; ?>) <?php echo htmlspecialchars($monster['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="item_id">アイテム:</label>
                <select id="item_id" name="item_id" required>
                    <option value="" disabled selected>-- アイテムを選択 --</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="drop_chance">ドロップ率 (%):</label>
                <input type="number" id="drop_chance" name="drop_chance" min="0.01" max="100" step="0.01" value="10.0" required>
                <span>%</span>
                <small>(例: 10% や 0.5% など)</small>
            </p>
            <button type="submit">ドロップ設定を追加</button>
        </form>

        <hr>
        <h2>現在のドロップ設定一覧</h2>
        <table>
            <tr>
                <th>モンスター</th>
                <th>ドロップアイテム</th>
                <th>ドロップ率</th>
                <th>操作</th>
            </tr>
            <?php foreach ($current_drops as $drop): ?>
            <tr>
                <td><?php echo htmlspecialchars($drop['monster_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($drop['item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo ($drop['drop_chance'] * 100); // 0.2 -> 20% にして表示 ?>%</td>
                <td>
                    <form action="admin_delete_drop.php" method="post" class="delete-form" onsubmit="return confirm('このドロップ設定を削除しますか？');">
                        <input type="hidden" name="id" value="<?php echo $drop['id']; ?>">
                        <button type="submit">削除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>