<?php
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');
$pdo = connectDb();
$player_id = $_SESSION['player_id'];

// プレイヤーの所持金を取得
$stmt_player = $pdo->prepare("SELECT gold FROM players WHERE player_id = :player_id");
$stmt_player->bindValue(':player_id', $player_id, PDO::PARAM_INT);
$stmt_player->execute();
$player_gold = $stmt_player->fetchColumn();

// 1. 販売リスト (buy_price > 0 のアイテム) を取得
$stmt_shop = $pdo->query("SELECT * FROM items WHERE buy_price > 0 ORDER BY id");
$shop_items = $stmt_shop->fetchAll();

// 2. プレイヤーの所持品 (売却用) を取得
$stmt_inventory = $pdo->prepare(
    "SELECT pi.inventory_id, i.name, i.buy_price
     FROM player_inventory pi
     JOIN items i ON pi.item_id = i.id
     WHERE pi.player_id = :player_id AND pi.is_equipped = 0" // 装備していないもののみ
);
$stmt_inventory->bindValue(':player_id', $player_id, PDO::PARAM_INT);
$stmt_inventory->execute();
$inventory_items = $stmt_inventory->fetchAll();

// 売却価格は買値の半額（切り捨て）
function getSellPrice($buy_price) {
    return floor($buy_price / 2);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>道具屋</title>
    <style>
        body { background-color: #333; color: #eee; font-family: sans-serif; }
        .container { max-width: 1000px; margin: 40px auto; padding: 20px; background-color: #282828; border: 1px solid #555; }
        h1, h2 { border-bottom: 1px solid #555; padding-bottom: 10px; }
        .back-link { display: inline-block; margin-top: 30px; color: #eee; }
        .shop-container { display: flex; gap: 30px; }
        .buy-section, .sell-section { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #555; padding: 8px; text-align: left; }
        th { background-color: #444; }
        .gold-display { font-size: 1.2em; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>道具屋</h1>
        <p class="gold-display">所持金: <?php echo htmlspecialchars($player_gold, ENT_QUOTES, 'UTF-8'); ?>G</p>
        
        <div class="shop-container">
            <div class="buy-section">
                <h2>商品を買う</h2>
                <table>
                    <tr><th>商品名</th><th>価格</th><th></th></tr>
                    <?php foreach ($shop_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $item['buy_price']; ?>G</td>
                        <td>
                            <form action="buy_item.php" method="post">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit">買う</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="sell-section">
                <h2>持ち物を売る</h2>
                <table>
                    <tr><th>アイテム名</th><th>売値</th><th></th></tr>
                    <?php if (empty($inventory_items)): ?>
                        <tr><td colspan="3">売れるアイテムがありません。</td></tr>
                    <?php else: ?>
                        <?php foreach ($inventory_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo getSellPrice($item['buy_price']); ?>G</td>
                            <td>
                                <form action="sell_item.php" method="post">
                                    <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                    <button type="submit">売る</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <hr>
        <a href="game.php" class="back-link">ゲームに戻る</a>
    </div>
</body>
</html>