<?php
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');
$player_id = $_SESSION['player_id'];

// equipment.php と同じスロット定義
$all_slots_map = [
    'right_hand' => '右手', 'left_hand' => '左手', 'head_top' => '頭上段', 'head_middle' => '頭中断',
    'head_bottom' => '頭下段', 'neck' => '首', 'body' => '体', 'arm' => '腕', 'waist' => '腰',
    'leg' => '足', 'foot' => '靴', 'accessory1' => 'アクセサリー1', 'accessory2' => 'アクセサリー2'
];

$items_for_sale = [];
$player_gold = 0;

try {
    $pdo = connectDb();
    
    // 1. プレイヤーの所持金を取得
    $stmt_player = $pdo->prepare("SELECT gold FROM players WHERE player_id = :player_id");
    $stmt_player->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt_player->execute();
    $player_gold = $stmt_player->fetchColumn();

    // 2. 「各スロットで最も価格が安い(＝弱い) 4件」を取得するSQL
    // (CTEとWindow関数を使用)
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
            -- スロットマップの順序で並び替える
            FIELD(equip_slot, 
                  'right_hand', 'left_hand', 'head_top', 'head_middle', 'head_bottom', 
                  'neck', 'body', 'arm', 'waist', 'leg', 'foot', 
                  'accessory1', 'accessory2'), 
            buy_price;
    ";
    
    $items_for_sale = $pdo->query($sql)->fetchAll();

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
        h1 { border-bottom: 1px solid #555; padding-bottom: 10px; }
        .gold-display { text-align: right; font-size: 1.2em; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-top: 30px; color: #eee; }
        
        /* ▼▼▼ (ここから) 陳列棚のスタイル ▼▼▼ */
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
        .item-buy-form { flex: 1; text-align: right; }
        .item-buy-form button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .item-buy-form button:hover { background-color: #0056b3; }
        .item-buy-form button:disabled {
            background-color: #555;
            cursor: not-allowed;
        }
        /* ▲▲▲ (ここまで) 陳列棚のスタイル ▲▲▲ */
    </style>
</head>
<body>
    <div class="container">
        <h1>道具屋</h1>
        <div class="gold-display">所持金: <?php echo htmlspecialchars($player_gold, ENT_QUOTES, 'UTF-8'); ?> G</div>
        <hr>

        <?php 
        $current_slot = null;
        foreach ($items_for_sale as $item): 
            // ▼ スロットが変わったら、新しい見出し (h2) を表示する
            if ($item['equip_slot'] !== $current_slot):
                // (前のグループの </div> を閉じる)
                if ($current_slot !== null) echo '</div>'; 
                
                $current_slot = $item['equip_slot'];
                $slot_name = $all_slots_map[$current_slot] ?? $current_slot;
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
        ?>

        <hr>
        <a href="game.php" class="back-link">ゲームに戻る</a>
    </div>
</body>
</html>