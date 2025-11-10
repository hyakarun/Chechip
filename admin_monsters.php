<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: index.php');
    exit();
}

require_once(__DIR__ . '/../db_connect.php'); // 階層が違うのでパスを修正

try {
    $pdo = connectDb();
    // ★ 取得するテーブルを news から monsters に変更
    // (★ base_drop_rate も取得)
    $stmt = $pdo->query("SELECT * FROM monsters ORDER BY id ASC");
    $monsters_list = $stmt->fetchAll();
} catch (PDOException $e) {
    exit('データベースエラー: '. $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>モンスター管理</title>
    <style>
        /* (dashboard.php と同じスタイルシート) */
        body { font-family: sans-serif; }
        table { border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background-color: #f4f4f4; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: inline-block; width: 100px; }
    </style>
</head>
<body>
    <h1>モンスター管理</h1>
    <a href="dashboard.php">お知らせ管理に戻る</a> | 
    <a href="logout.php">ログアウト</a>
    <hr>
    
    <h2>新しいモンスターを追加</h2>
    <form action="admin_add_monster.php" method="post">
        <div class="form-group">
            <label for="name">名前:</label>
            <input type="text" name="name" size="30" required>
        </div>
        <div class="form-group">
            <label for="level">Level:</label>
            <input type="number" name="level" value="1" required>
        </div>
        <div class="form-group">
            <label for="exp">EXP:</label>
            <input type="number" name="exp" value="10" required>
        </div>
        <div class="form-group">
            <label for="hp">HP:</label>
            <input type="number" name="hp" value="100" required>
        </div>
        <div class="form-group">
            <label for="atk">ATK:</label>
            <input type="number" name="atk" value="10" required>
        </div>
        <div class="form-group">
            <label for="def">DEF:</label>
            <input type="number" name="def" value="10" required>
        </div>
        <div class="form-group">
            <label for="strength">Strength:</label>
            <input type="number" name="strength" value="10" required>
        </div>
        <div class="form-group">
            <label for="vitality">Vitality:</label>
            <input type="number" name="vitality" value="10" required>
        </div>
        <div class="form-group">
            <label for="intelligence">Intelligence:</label>
            <input type="number" name="intelligence" value="10" required>
        </div>
        <div class="form-group">
            <label for="speed">Speed:</label>
            <input type="number" name="speed" value="10" required>
        </div>
        <div class="form-group">
            <label for="luck">Luck:</label>
            <input type="number" name="luck" value="10" required>
        </div>
        <div class="form-group">
            <label for="charisma">Charisma:</label>
            <input type="number" name="charisma" value="10" required>
        </div>
        <div class="form-group">
            <label for="gold">Gold:</label>
            <input type="number" name="gold" value="5" required>
        </div>
        
        <div class="form-group">
            <label for="base_drop_rate">基本ドロップ率(%):</label>
            <input type="number" name="base_drop_rate" value="20" min="0" max="100" step="0.1" required> %
            <small>(例: 雑魚=20%, ボス=50%)</small>
        </div>
        <div class="form-group">
            <label for="image">画像:</label>
            <input type="text" name="image" size="30" placeholder="slime.png">
        </div>
        <button type="submit">追加する</button>
    </form>
    <hr>
    
    <h2>モンスター一覧</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>名前</th>
            <th>LV</th>
            <th>HP</th>
            <th>EXP</th>
            <th>Gold</th>
            <th>基本ドロップ率</th> <th>画像</th>
            <th>操作</th>
        </tr>
        <?php foreach ($monsters_list as $monster): ?>
        <tr>
            <td><?php echo htmlspecialchars($monster['id'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($monster['name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($monster['level'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($monster['hp'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($monster['exp'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($monster['gold'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo ($monster['base_drop_rate'] * 100); ?>%</td>
            <td><?php echo htmlspecialchars($monster['image'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
                (編集) (削除)
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>