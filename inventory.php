<?php
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');

// 定数
define('MAX_INVENTORY_SLOTS', 100);

$inventory_list = [];
$current_slots = 0;

try {
    $pdo = connectDb();

    // ▼▼▼ 修正: 存在するアイテム(itemsテーブルにあるもの)だけをカウントするように変更 ▼▼▼
    $sql_count = "
        SELECT COUNT(pi.inventory_id) 
        FROM player_inventory pi
        JOIN items i ON pi.item_id = i.id
        WHERE pi.player_id = :player_id
    ";
    $stmt_total_slots = $pdo->prepare($sql_count);
    $stmt_total_slots->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $stmt_total_slots->execute();
    $current_slots = $stmt_total_slots->fetchColumn();
    // ▲▲▲ 修正ここまで ▲▲▲
    
    // 2. 所持品リストを取得
    $sql = "
        SELECT pi.inventory_id, pi.is_equipped, pi.quantity, i.name, i.type, i.equip_slot, i.not_stackable
        FROM player_inventory pi
        JOIN items i ON pi.item_id = i.id
        WHERE pi.player_id = :player_id
        ORDER BY 
            pi.is_equipped DESC, -- 装備中を一番上に
            i.type ASC,          -- 種類順
            i.name ASC,          -- 名前順
            pi.inventory_id ASC  -- 入手順
    ";

    $stmt = $pdo->prepare($sql);
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
        
        .header-area { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #555; padding-bottom: 10px; margin-bottom: 20px; }
        h1 { margin: 0; }
        .header-controls { text-align: right; }
        .slot-display { font-size: 1.1em; font-weight: bold; color: #8af; margin-bottom: 5px; }
        
        .organize-button {
            background-color: #d39e00; color: #fff; border: 1px solid #c69500; 
            padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 0.9em;
        }
        .organize-button:hover { background-color: #e0a800; }

        table { width: 100%; border-collapse: collapse; margin-top: 0; }
        th, td { border: 1px solid #555; padding: 10px; text-align: left; }
        th { background-color: #444; }
        .back-link { display: inline-block; margin-top: 30px; color: #eee; }
        
        .equipped-status { color: #4caf50; }
        .unequipped-status { color: #aaa; }
        .item-name-link { color: #eee; text-decoration: none; cursor: default; }
        .item-name-link:hover { color: #8af; }
        .quantity-display { color: #8af; font-weight: bold; margin-left: 10px; }
        .stack-info { font-size: 0.8em; color: #888; margin-left: 5px; }

        #item-tooltip {
            display: none; position: absolute; background-color: #111;
            border: 1px solid #777; padding: 10px; border-radius: 4px;
            z-index: 2000; pointer-events: none; max-width: 300px;
        }
        #item-tooltip h3 { margin: 0 0 10px; padding: 0; border: none; font-size: 1.1em; }
        #item-tooltip ul { margin: 0; padding-left: 15px; }
        #item-tooltip li { font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-area">
            <h1>所持品</h1>
            <div class="header-controls">
                <div class="slot-display">
                    スロット: <?php echo $current_slots; ?> / <?php echo MAX_INVENTORY_SLOTS; ?>
                </div>
                <form action="organize_items.php" method="post" style="display:inline;" onsubmit="return confirm('アイテムを整理しますか？\n・スタック可能なアイテムを99個ずつにまとめます\n・装備品は整理されません');">
                    <button type="submit" class="organize-button">せいとん</button>
                </form>
            </div>
        </div>

        <?php if (empty($inventory_list)): ?>
            <p>所持品はなにもない。</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>アイテム名</th>
                    <th>種類</th>
                    <th>状態 / 数量</th> 
                </tr>
                <?php foreach ($inventory_list as $item): ?>
                <tr>
                    <td>
                        <a href="#" class="item-name-link" data-hover-id="<?php echo $item['inventory_id']; ?>" onclick="return false;">
                            <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        
                        <?php if ($item['type'] === 'equipment'): ?>
                            <?php else: ?>
                            <span class="quantity-display">x<?php echo $item['quantity']; ?></span>
                            <?php if(isset($item['not_stackable']) && $item['not_stackable'] == 1): ?>
                                <span class="stack-info">(スタック不可)</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            if ($item['type'] === 'equipment') echo '装備品';
                            elseif ($item['type'] === 'usable') echo '道具';
                            else echo 'その他';
                        ?>
                    </td>
                    <td>
                        <?php if ($item['type'] === 'equipment'): ?>
                            <?php if ($item['is_equipped']): ?>
                                <span class="equipped-status">装備中</span>
                            <?php else: ?>
                                <span class="unequipped-status">未装備</span>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <hr>

        <a href="game.php" class="back-link">ゲームに戻る</a>
    </div>

    <div id="item-tooltip">
        <h3 id="tooltip-item-name"></h3>
        <ul id="tooltip-item-options"></ul>
    </div>

    <script>
        const itemDetailsCache = new Map();
        const tooltip = document.getElementById('item-tooltip');
        const tooltipName = document.getElementById('tooltip-item-name');
        const tooltipOptions = document.getElementById('tooltip-item-options');
        
        async function fetchItemDetails(inventoryId) {
            if (itemDetailsCache.has(inventoryId)) { return itemDetailsCache.get(inventoryId); }
            try {
                const response = await fetch(`get_item_details.php?inventory_id=${inventoryId}`);
                const data = await response.json();
                itemDetailsCache.set(inventoryId, data);
                return data;
            } catch (error) { return { error: '通信エラー' }; }
        }

        function showTooltip(data, event) {
            if (!data || data.error) return;
            tooltipName.textContent = '';
            tooltipOptions.innerHTML = '';
            if (data.name) {
                tooltipName.textContent = data.name;
                if (data.options && data.options.length > 0) {
                    data.options.forEach(option => {
                        const li = document.createElement('li');
                        if (option.type === 'guaranteed') {
                            li.innerHTML = `<strong>${option.text}</strong>`;
                        } else {
                            li.textContent = option.text;
                        }
                        tooltipOptions.appendChild(li);
                    });
                } else {
                    tooltipOptions.innerHTML = '<li>特別な効果はありません。</li>';
                }
                tooltip.style.left = (event.pageX + 15) + 'px';
                tooltip.style.top = (event.pageY + 15) + 'px';
                tooltip.style.display = 'block';
            }
        }

        document.querySelectorAll('.item-name-link').forEach(element => {
            element.addEventListener('mouseover', async (event) => {
                const inventoryId = element.dataset.hoverId;
                if (inventoryId) {
                    const details = await fetchItemDetails(inventoryId);
                    showTooltip(details, event);
                }
            });
            element.addEventListener('mouseout', () => { tooltip.style.display = 'none'; });
            element.addEventListener('mousemove', (event) => {
                tooltip.style.left = (event.pageX + 15) + 'px';
                tooltip.style.top = (event.pageY + 15) + 'px';
            });
        });
    </script>
</body>
</html>