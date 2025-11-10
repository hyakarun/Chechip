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
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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

        .batch-form-container { display: flex; justify-content: space-between; gap: 20px; }
        .batch-form-container .select-box { flex: 1; }
        .batch-form-container .select-box label { font-weight: bold; }
        .batch-form-container .select-box small { display: block; margin-top: 5px; color: #555; }
        .batch-form-container .config-box { width: 200px; }
        
        .select2-container {
            width: 100% !important; 
        }
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

        <h2>新しいドロップを一括登録</h2>
        
        <form action="admin_handle_drop.php" method="post">
            <div class="batch-form-container">
                
                <div class="select-box">
                    <label for="monster_id">1. モンスターを選択 (複数可)</label>
                    <select id="monster_id" name="monster_id[]" multiple required class="select2-multiple">
                        <?php foreach ($monsters as $monster): ?>
                            <option value="<?php echo $monster['id']; ?>">
                                (Lv<?php echo $monster['level']; ?>) <?php echo htmlspecialchars($monster['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>文字入力で検索できます。選んだ項目はタグとして蓄積されます。</small>
                </div>

                <div class="select-box">
                    <label for="item_id">2. アイテムを選択 (複数可)</label>
                    <select id="item_id" name="item_id[]" multiple required class="select2-multiple">
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>文字入力で検索できます。選んだ項目はタグとして蓄積されます。</small>
                </div>

                <div class="config-box">
                    <p>
                        <label for="drop_chance" style="font-weight: bold;">3. ドロップの「重み」:</label>
                        <input type="number" id="drop_chance" name="drop_chance" min="1" step="1" value="10" required style="width: 100px;">
                        <small>(例: 武器=20, 鎧=30, 盾=50 のように、相対的な確率の「重み」を入力。%ではありません)</small>
                        </p>
                    <button type="submit" style="padding: 10px 20px; font-weight: bold; width: 100%;">
                        上記の内容で一括登録
                    </button>
                </div>

            </div>
        </form>

        <hr>
        <h2>現在のドロップ設定一覧</h2>
        <table>
            <tr>
                <th>モンスター</th>
                <th>ドロップアイテム</th>
                <th>ドロップの「重み」</th> <th>操作</th>
            </tr>
            <?php foreach ($current_drops as $drop): ?>
            <tr>
                <td><?php echo htmlspecialchars($drop['monster_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($drop['item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($drop['drop_chance'], ENT_QUOTES, 'UTF-8'); ?></td> <td>
                    <form action="admin_delete_drop.php" method="post" class="delete-form" onsubmit="return confirm('このドロップ設定を削除しますか？');">
                        <input type="hidden" name="id" value="<?php echo $drop['id']; ?>">
                        <button type="submit">削除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <script>
        $(document).ready(function() {
            // "select2-multiple" というクラスを持つすべてのselectタグを、Select2 UIに置き換える
            $('.select2-multiple').select2({
                placeholder: "クリックして検索・選択...", // 何も選択されていない時の表示
                allowClear: true
            });
        });
    </script>
    </body>
</html>