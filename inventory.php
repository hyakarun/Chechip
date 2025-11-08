<?php
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');

// プレイヤーの所持品リストを取得
$inventory_list = [];
try {
    $pdo = connectDb();
    
    // player_inventory と items テーブルを JOIN して、アイテム名や種類、所持数を一度に取得
    $stmt = $pdo->prepare(
        "SELECT i.name, i.type, i.equip_slot, COUNT(i.id) as quantity
         FROM player_inventory pi
         JOIN items i ON pi.item_id = i.id
         WHERE pi.player_id = :player_id
         GROUP BY i.id, i.name, i.type, i.equip_slot
         ORDER BY i.type, i.name"
    );
    $stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $stmt->execute();
    $inventory_list = $stmt->fetchAll();

} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>所持品</title>
    <style>
        body { background-color: #333; color: #eee; font-family: sans-serif; }
        .container { max-width: 800px; margin: 40px auto; padding: 20px; background-color: #282828; border: 1px solid #555; }
        h1 { border-bottom: 1px solid #555; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #555; padding: 10px; text-align: left; }
        th { background-color: #444; }
        .back-link { display: inline-block; margin-top: 30px; color: #eee; }
    </style>
</head>
<body>
    <div class="container">
        <h1>所持品</h1>

        <?php if (empty($inventory_list)): ?>
            <p>所持品はなにもない。</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>アイテム名</th>
                    <th>種類</th>
                    <th>数量</th>
                    <th>操作</th>
                </tr>
                <?php foreach ($inventory_list as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php 
                            if ($item['type'] === 'equipment') echo '装備品';
                            elseif ($item['type'] === 'usable') echo '道具';
                            else echo 'その他';
                        ?>
                    </td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>
                        <?php if ($item['type'] === 'equipment'): ?>
                            <button disabled>装備する</button> <?php elseif ($item['type'] === 'usable'): ?>
                            <button disabled>使う</button> <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <hr>

        <a href="game.php" class="back-link">ゲームに戻る</a>
    </div>
</body>
</html>