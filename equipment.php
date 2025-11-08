<?php
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');
$pdo = connectDb();

// 1. 全ての装備スロットを定義
$all_slots = [
    'right_hand' => '右手', 'left_hand' => '左手', 'head_top' => '頭上段', 'head_middle' => '頭中断',
    'head_bottom' => '頭下段', 'neck' => '首', 'body' => '体', 'arm' => '腕', 'waist' => '腰',
    'leg' => '足', 'foot' => '靴', 'accessory1' => 'アクセサリー1', 'accessory2' => 'アクセサリー2'
];

// 2. プレイヤーが現在「装備中」のアイテムを取得
$currently_equipped = [];
$stmt_equipped = $pdo->prepare(
    "SELECT pi.inventory_id, pi.item_id, i.name, i.equip_slot
     FROM player_inventory pi
     JOIN items i ON pi.item_id = i.id
     WHERE pi.player_id = :player_id AND pi.is_equipped = 1"
);
$stmt_equipped->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
$stmt_equipped->execute();
foreach ($stmt_equipped->fetchAll() as $item) {
    $currently_equipped[$item['equip_slot']] = $item;
}

// 3. プレイヤーが所持している「未装備」のアイテムを、スロット別に分類
$available_inventory = [];
$stmt_inventory = $pdo->prepare(
    "SELECT pi.inventory_id, i.name, i.equip_slot
     FROM player_inventory pi
     JOIN items i ON pi.item_id = i.id
     WHERE pi.player_id = :player_id AND pi.is_equipped = 0 AND i.type = 'equipment'"
);
$stmt_inventory->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
$stmt_inventory->execute();
foreach ($stmt_inventory->fetchAll() as $item) {
    $slot = $item['equip_slot'];
    if (!isset($available_inventory[$slot])) {
        $available_inventory[$slot] = [];
    }
    $available_inventory[$slot][] = $item;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>装備変更</title>
    <style>
        body { background-color: #333; color: #eee; font-family: sans-serif; }
        .container { max-width: 1000px; margin: 40px auto; padding: 20px; background-color: #282828; border: 1px solid #555; }
        h1 { border-bottom: 1px solid #555; padding-bottom: 10px; }
        .back-link { display: inline-block; margin-top: 30px; color: #eee; }
        .equipment-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .equipment-table th, .equipment-table td { border: 1px solid #555; padding: 10px; text-align: left; }
        .equipment-table th { background-color: #444; }
        .slot-name { width: 20%; font-weight: bold; }
        .current-item { width: 40%; white-space: nowrap; }
        .equip-form { width: 40%; }
        .equip-form select { width: 60%; }
        .detail-button { margin-left: 5px; }
        
        /* 装備中アイテムのホバー用 */
        .equipped-item-link { color: #eee; text-decoration: none; cursor: default; }
        .equipped-item-link:hover { color: #8af; }

        /* モーダル用CSS (変更なし) */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background-color: #282828; border: 1px solid #777; padding: 20px; min-width: 300px; max-width: 500px; border-radius: 5px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; border: none; }
        .modal-close { cursor: pointer; font-size: 1.5em; }
        .modal-body ul { margin-top: 20px; padding-left: 20px; }

        /* ▼▼▼ ツールチップ用のCSS ▼▼▼ */
        #item-tooltip {
            display: none; /* 初期状態では隠す */
            position: absolute; /* マウスに追従させる */
            background-color: #111;
            border: 1px solid #777;
            padding: 10px;
            border-radius: 4px;
            z-index: 2000; /* モーダルより手前 */
            pointer-events: none; /* マウス操作を邪魔しないように */
            max-width: 300px;
        }
        #item-tooltip h3 { margin: 0 0 10px; padding: 0; border: none; }
        #item-tooltip ul { margin: 0; padding-left: 15px; }
        #item-tooltip li { font-size: 0.9em; }
        /* ▲▲▲ ここまで ▲▲▲ */
    </style>
</head>
<body>
    <div class="container">
        <h1>装備変更</h1>
        <table class="equipment-table">
            <tr>
                <th>装備箇所</th>
                <th>現在の装備</th>
                <th>装備を変更する</th>
            </tr>
            <?php foreach ($all_slots as $slot_key => $slot_name): ?>
            <tr>
                <td class="slot-name"><?php echo $slot_name; ?></td>
                <td class="current-item">
                    <?php if (isset($currently_equipped[$slot_key])): $item = $currently_equipped[$slot_key]; ?>
                        <a href="#" class="equipped-item-link" data-hover-id="<?php echo $item['inventory_id']; ?>" onclick="return false;">
                            <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <button class="detail-button" data-inventory-id="<?php echo $item['inventory_id']; ?>">詳細</button>
                        ( <a href="unequip_item.php?inventory_id=<?php echo $item['inventory_id']; ?>">外す</a> )
                    <?php else: ?>
                        なし
                    <?php endif; ?>
                </td>
                <td class="equip-form">
                    <form action="equip_item.php" method="post" style="display:inline;">
                        <input type="hidden" name="slot" value="<?php echo $slot_key; ?>">
                        <select name="inventory_id" required data-hover-id="select-<?php echo $slot_key; ?>">
                            <option value="" disabled selected>装備品を選択...</option>
                            <?php if (!empty($available_inventory[$slot_key])): ?>
                                <?php foreach ($available_inventory[$slot_key] as $item): ?>
                                    <option value="<?php echo $item['inventory_id']; ?>">
                                        <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <button type="submit">装備</button>
                    </form>
                    <?php if (!empty($available_inventory[$slot_key])): ?>
                        <button class="detail-button">詳細</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <hr>
        <a href="game.php" class="back-link">ゲームに戻る</a>
    </div>

    <div id="item-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-item-name">アイテム名</h2>
                <span id="modal-close" class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <ul id="modal-item-options"></ul>
            </div>
        </div>
    </div>

    <div id="item-tooltip">
        <h3 id="tooltip-item-name"></h3>
        <ul id="tooltip-item-options"></ul>
    </div>
    <script>
        // --- キャッシュとモーダルの要素 ---
        const itemDetailsCache = new Map();
        const modal = document.getElementById('item-modal');
        const modalName = document.getElementById('modal-item-name');
        const modalOptions = document.getElementById('modal-item-options');
        const closeModal = document.getElementById('modal-close');
        
        // --- ツールチップの要素 ---
        const tooltip = document.getElementById('item-tooltip');
        const tooltipName = document.getElementById('tooltip-item-name');
        const tooltipOptions = document.getElementById('tooltip-item-options');

        // --- 詳細ボタン（クリック）の処理 ---
        document.querySelectorAll('.detail-button').forEach(button => {
            button.addEventListener('click', async (event) => {
                let inventoryId;
                if (event.target.dataset.inventoryId) {
                    inventoryId = event.target.dataset.inventoryId;
                } else {
                    const formCell = event.target.closest('.equip-form');
                    if (formCell) {
                        const select = formCell.querySelector('select[name="inventory_id"]');
                        if (select && select.value) {
                            inventoryId = select.value;
                        } else {
                            alert('先にプルダウンからアイテムを選択してください。');
                            return;
                        }
                    }
                }
                if (inventoryId) {
                    const details = await fetchItemDetails(inventoryId);
                    showModal(details);
                }
            });
        });

        // --- ホバー（マウスオーバー）の処理 ---
        document.querySelectorAll('[data-hover-id]').forEach(element => {
            element.addEventListener('mouseover', async (event) => {
                let inventoryId;
                if (element.tagName === 'SELECT') {
                    // プルダウンの場合、現在選択されている値
                    if(element.value) inventoryId = element.value;
                } else {
                    // 装備中アイテム（aタグ）の場合
                    inventoryId = element.dataset.hoverId;
                }
                
                if (inventoryId) {
                    const details = await fetchItemDetails(inventoryId);
                    showTooltip(details, event);
                }
            });

            element.addEventListener('mouseout', () => {
                tooltip.style.display = 'none';
            });
            
            element.addEventListener('mousemove', (event) => {
                tooltip.style.left = (event.pageX + 15) + 'px';
                tooltip.style.top = (event.pageY + 15) + 'px';
            });
        });


        // --- アイテム詳細を取得する共通関数（キャッシュ機能付き） ---
        async function fetchItemDetails(inventoryId) {
            // 1. キャッシュにあればそれを返す
            if (itemDetailsCache.has(inventoryId)) {
                return itemDetailsCache.get(inventoryId);
            }
            
            // 2. なければDBに問い合わせる
            try {
                const response = await fetch(`get_item_details.php?inventory_id=${inventoryId}`);
                const data = await response.json();
                
                // 3. 結果をキャッシュに保存して返す
                itemDetailsCache.set(inventoryId, data);
                return data;
            } catch (error) {
                return { error: '通信エラー' };
            }
        }

        // --- モーダル（ポップアップ）を表示する関数 ---
        function showModal(data) {
            if (!data) return;
            modalName.textContent = '読み込み中...';
            modalOptions.innerHTML = '';
            
            if (data.error) {
                modalName.textContent = 'エラー';
                modalOptions.innerHTML = `<li>${data.error}</li>`;
            } else {
                modalName.textContent = data.name;
                if (data.options && data.options.length > 0) {
                    // ★★★ バグ修正箇所 ★★★
                    data.options.forEach(option => {
                        const li = document.createElement('li');
                        if (option.type === 'guaranteed') {
                            li.innerHTML = `<strong>${option.text}</strong>`;
                        } else {
                            li.textContent = option.text;
                        }
                        modalOptions.appendChild(li);
                    });
                } else {
                    modalOptions.innerHTML = '<li>特別な効果はありません。</li>';
                }
            }
            modal.style.display = 'flex';
        }
        
        // --- ツールチップを表示する関数 ---
        function showTooltip(data, event) {
            if (!data) return;
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

        // --- モーダルを閉じる処理 ---
        closeModal.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>