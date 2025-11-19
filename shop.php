<?php
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');
$player_id = $_SESSION['player_id'];
$mode = $_GET['mode'] ?? 'buy'; // 'buy' (default) or 'sell'

// equipment.php と同じスロット定義 (買物リストの表示用)
$all_slots_map = [
    'right_hand' => '右手', 'left_hand' => '左手', 'head_top' => '頭上段', 'head_middle' => '頭中断',
    'head_bottom' => '頭下段', 'neck' => '首', 'body' => '体', 'arm' => '腕', 'waist' => '腰',
    'leg' => '足', 'foot' => '靴', 'accessory1' => 'アクセサリー1', 'accessory2' => 'アクセサリー2'
];

$items_for_sale = []; // 買物リスト
$items_to_sell = [];   // 売却リスト
$player_gold = 0;

try {
    $pdo = connectDb();
    
    // 1. プレイヤーの所持金を取得
    $stmt_player = $pdo->prepare("SELECT gold FROM players WHERE player_id = :player_id");
    $stmt_player->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_player->execute();
    $player_gold = $stmt_player->fetchColumn();

    if ($mode === 'buy') {
        // 2. 「各スロットで最も価格が安い(＝弱い) 4件」を取得するSQL
        $sql = "
            WITH RankedItems AS (
                SELECT 
                    id, name, equip_slot, buy_price,
                    ROW_NUMBER() OVER(
                        PARTITION BY equip_slot 
                        ORDER BY buy_price ASC, id ASC
                    ) AS rank_in_slot
                FROM items
                WHERE 
                    type = 'equipment' AND 
                    buy_price > 0 -- 価格が設定されているもののみ
            )
            SELECT id, name, equip_slot, buy_price
            FROM RankedItems
            WHERE rank_in_slot <= 4
            ORDER BY 
                -- hand に対応するため修正
                FIELD(equip_slot, 
                      'right_hand', 'left_hand', 'hand', 'head_top', 'head_middle', 'head_bottom', 
                      'neck', 'body', 'arm', 'waist', 'leg', 'foot', 
                      'accessory1', 'accessory2'), 
                buy_price;
        ";
        
        $items_for_sale = $pdo->query($sql)->fetchAll();
    
    } elseif ($mode === 'sell') {
        // 2. プレイヤーの所持品で、売却可能なもの(buy_price > 0)をリストアップ
        
        // 装備品 (個別の行) - 装備中は売れないがリストに表示
        $stmt_equipment = $pdo->prepare(
            "SELECT pi.inventory_id, i.name, i.buy_price, pi.quantity, pi.is_equipped, i.type
             FROM player_inventory pi
             JOIN items i ON pi.item_id = i.id
             WHERE pi.player_id = :player_id AND i.buy_price > 0 AND i.type = 'equipment'
             ORDER BY pi.inventory_id"
        );
        $stmt_equipment->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $stmt_equipment->execute();
        $items_to_sell = $stmt_equipment->fetchAll();

        // 消費アイテム/その他 (スタックされているもの)
        // 数量を集計し、代表の inventory_id (MIN) を使用 (売却時の inventory_id はそのスタックの代表ID)
        $stmt_stackable = $pdo->prepare(
            "SELECT MIN(pi.inventory_id) AS inventory_id, i.name, i.buy_price, SUM(pi.quantity) AS quantity, i.type
             FROM player_inventory pi
             JOIN items i ON pi.item_id = i.id
             WHERE pi.player_id = :player_id AND i.buy_price > 0 AND i.type IN ('usable', 'etc')
             GROUP BY i.name, i.buy_price, i.type
             ORDER BY i.type, i.name"
        );
        $stmt_stackable->bindValue(':player_id', $player_id, PDO::PARAM_INT);
        $stmt_stackable->execute();
        
        // 結果を結合
        foreach($stmt_stackable->fetchAll() as $item) {
            $item['is_equipped'] = 0; // 消費アイテムは装備できない
            $items_to_sell[] = $item;
        }
    }

} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>道具屋</title>
    <style>
        body { background-color: #333; color: #eee; font-family: sans-serif; }
        .container { max-width: 900px; margin: 40px auto; padding: 20px; background-color: #282828; border: 1px solid #555; }
        .shop-header { border-bottom: 1px solid #555; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        h1 { margin: 0; }
        .gold-display { text-align: right; font-size: 1.2em; }
        
        /* タブのスタイル */
        .shop-tabs { margin-bottom: 20px; }
        .shop-tabs a {
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            color: #ccc;
            border: 1px solid #555;
            border-bottom: none;
            background-color: #333;
            margin-right: -1px;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }
        .shop-tabs a.active {
            color: #fff;
            background-color: #282828;
            border-color: #555;
            font-weight: bold;
        }
        
        /* 共通のテーブルスタイル (売却リスト用) */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background-color: #444; }
        
        /* 購入リスト用のスタイル */
        .slot-group { margin-bottom: 30px; }
        .slot-group h2 { 
            background-color: #444; 
            padding: 10px; 
            border-bottom: 2px solid #666; 
            margin-top: 0;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #444;
        }
        .item-row:nth-child(even) { background-color: #303030; }
        .item-name { flex: 3; font-size: 1.1em; }
        .item-price { flex: 2; text-align: right; padding-right: 20px; }
        .item-buy-form, .item-sell-form { flex: 1; text-align: right; }
        
        /* ボタン共通 */
        button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover { background-color: #0056b3; }
        button:disabled {
            background-color: #555;
            cursor: not-allowed;
        }

        /* 売却ボタンの色を赤系に */
        .sell-button {
            background-color: #cc3333;
        }
        .sell-button:hover {
            background-color: #aa1111;
        }
        
        .back-link { display: inline-block; margin-top: 30px; color: #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="shop-header">
            <h1>道具屋</h1>
            <div class="gold-display">所持金: <?php echo htmlspecialchars($player_gold, ENT_QUOTES, 'UTF-8'); ?> G</div>
        </div>

        <div class="shop-tabs">
            <a href="shop.php?mode=buy" class="<?php echo ($mode === 'buy' ? 'active' : ''); ?>">購入</a>
            <a href="shop.php?mode=sell" class="<?php echo ($mode === 'sell' ? 'active' : ''); ?>">売却</a>
        </div>

        <?php if ($mode === 'buy'): ?>
            <?php 
            $current_slot = null;
            if (empty($items_for_sale)): ?>
                <p>現在、購入できる装備品はありません。</p>
            <?php else:
                foreach ($items_for_sale as $item): 
                    // スロットが変わったら、新しい見出し (h2) を表示する
                    if ($item['equip_slot'] !== $current_slot):
                        if ($current_slot !== null) echo '</div>'; 
                        
                        $current_slot = $item['equip_slot'];
                        // handは右手/左手の両方で使われる可能性があるため、表示名を調整
                        if ($current_slot === 'hand') {
                            $slot_name = '武器/盾 (手)';
                        } else {
                            $slot_name = $all_slots_map[$current_slot] ?? $current_slot;
                        }
                ?>
                <div class="slot-group">
                    <h2><?php echo htmlspecialchars($slot_name, ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php 
                    endif; 
                ?>
                    
                    <div class="item-row">
                        <span class="item-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="item-price"><?php echo htmlspecialchars($item['buy_price'], ENT_QUOTES, 'UTF-8'); ?> G</span>
                        <div class="item-buy-form">
                            <form action="buy_item.php" method="post">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" 
                                    <?php echo ($player_gold < $item['buy_price']) ? 'disabled' : ''; ?>
                                >
                                    <?php echo ($player_gold < $item['buy_price']) ? '所持金不足' : '購入'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                
                <?php 
                endforeach;
                // 最後のグループの </div> を閉じる
                if ($current_slot !== null) echo '</div>'; 
            endif;
            ?>

        <?php elseif ($mode === 'sell'): ?>
            <h2>売却可能なアイテム</h2>
            <?php if (empty($items_to_sell)): ?>
                <p>現在、売却可能なアイテム（装備中のアイテム、非売品を除く）はありません。</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>アイテム名</th>
                            <th>種類</th>
                            <th>売却価格 (1個あたり)</th>
                            <th>数量</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items_to_sell as $item): 
                            $sell_price = floor($item['buy_price'] / 2);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php 
                                    if ($item['type'] === 'equipment') echo '装備品';
                                    elseif ($item['type'] === 'usable') echo '道具';
                                    else echo 'その他';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($sell_price, ENT_QUOTES, 'UTF-8'); ?> G</td>
                            <td>
                                <?php if ($item['type'] === 'equipment'): ?>
                                    <?php echo ($item['is_equipped'] ? '装備中' : '1'); ?>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="item-sell-form">
                                    <form action="sell_item.php" method="post" onsubmit="return confirm('<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>を1つ (<?php echo $sell_price; ?> G) 売却しますか？');">
                                        <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                        <button type="submit" class="sell-button" 
                                            <?php echo ($item['is_equipped'] ? 'disabled' : ''); ?>
                                        >
                                            <?php echo ($item['is_equipped'] ? '装備中' : '売却'); ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>

        <hr>
        <a href="game.php" class="back-link">ゲームに戻る</a>
    </div>
</body>
</html>