<?php
session_start();
// ※ 診断用のini_setコードは削除しました。

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

// 2. プレイヤーが現在「装備中」のアイテムを取得 (equipped_slot を使用)
$currently_equipped = [];
$stmt_equipped = $pdo->prepare(
    "SELECT pi.inventory_id, pi.item_id, pi.equipped_slot, i.name, i.equip_slot as item_type
     FROM player_inventory pi
     JOIN items i ON pi.item_id = i.id
     WHERE pi.player_id = :player_id AND pi.is_equipped = 1"
);
$stmt_equipped->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
$stmt_equipped->execute();
foreach ($stmt_equipped->fetchAll() as $item) {
    // 装備位置は player_inventory.equipped_slot を優先
    $slot = !empty($item['equipped_slot']) ? $item['equipped_slot'] : $item['item_type'];
    $currently_equipped[$slot] = $item;
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
    $type = $item['equip_slot'];
    
    // hand属性のアイテムを右手と左手の両方の候補に入れる
    if ($type === 'hand') {
        // まだ配列が初期化されていなければ初期化
        if (!isset($available_inventory['right_hand'])) { $available_inventory['right_hand'] = []; }
        if (!isset($available_inventory['left_hand'])) { $available_inventory['left_hand'] = []; }

        $available_inventory['right_hand'][] = $item;
        $available_inventory['left_hand'][] = $item;
    } else {
        if (!isset($available_inventory[$type])) {
            $available_inventory[$type] = [];
        }
        $available_inventory[$type][] = $item;
    }
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
        .equipment-table th, .equipment-table td { border: 1px solid #555; padding: 10px; text-align: left; vertical-align: middle; }
        .equipment-table th { background-color: #444; }
        .slot-name { width: 15%; font-weight: bold; }
        .current-item { width: 35%; }
        .equip-form { width: 50%; }
        .equip-form select { width: 60%; padding: 5px; }
        .equipped-item-link { color: #eee; text-decoration: none; font-weight: bold; }
        .equipped-item-link:hover { color: #8af; }
        
        /* ボタンのデザイン */
        button { cursor: pointer; padding: 5px 10px; border-radius: 4px; border: 1px solid #666; background-color: #555; color: #fff; }
        button:hover { background-color: #666; }
        button:disabled { background-color: #444; color: #777; cursor: not-allowed; border-color: #444; }
        
        .compare-button { margin-left: 5px; background-color: #007bff; border-color: #007bff; }
        .compare-button:hover { background-color: #0056b3; border-color: #0056b3; }
        .compare-button:disabled { background-color: #444; border-color: #444; }

        /* モーダル */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background-color: #282828; border: 1px solid #777; padding: 20px; min-width: 400px; max-width: 600px; border-radius: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #555; padding-bottom: 10px; }
        .modal-header h2 { margin: 0; font-size: 1.4em; }
        .modal-close { cursor: pointer; font-size: 1.5em; color: #aaa; }
        .modal-close:hover { color: #fff; }
        
        /* 比較テーブル */
        .comparison-table { width: 100%; border-collapse: collapse; }
        .comparison-table th, .comparison-table td { border: 1px solid #555; padding: 8px; text-align: center; }
        .comparison-table th { background-color: #444; font-size: 0.9em; }
        .diff-positive { color: #4caf50; font-size: 0.9em; } /* 緑 */
        .diff-negative { color: #f44336; font-size: 0.9em; } /* 赤 */
        .diff-zero { color: #777; font-size: 0.9em; }
        .stat-label { text-align: left; font-weight: bold; background-color: #333; }
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
                    <?php 
                        $current_id = null;
                        $current_name = 'なし';
                        if (isset($currently_equipped[$slot_key])) {
                            $item = $currently_equipped[$slot_key];
                            $current_id = $item['inventory_id'];
                            $current_name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
                        }
                    ?>
                    <span id="current-equipped-<?php echo $slot_key; ?>" data-inventory-id="<?php echo $current_id; ?>">
                        <?php if ($current_id): ?>
                            <a href="#" class="equipped-item-link" onclick="return false;"><?php echo $current_name; ?></a>
                            <a href="unequip_item.php?inventory_id=<?php echo $current_id; ?>" 
                               style="color: #f44336; margin-left: 10px; text-decoration: none; font-size: 0.9em;">[外す]</a>
                        <?php else: ?>
                            なし
                        <?php endif; ?>
                    </span>
                </td>
                <td class="equip-form">
                    <form action="equip_item.php" method="post" style="display:inline; width: 100%;">
                        <input type="hidden" name="slot" value="<?php echo $slot_key; ?>">
                        
                        <select name="inventory_id" class="equip-select" data-slot="<?php echo $slot_key; ?>" required>
                            <option value="" selected>装備品を選択...</option>
                            <?php 
                                // 初期化されていない場合に備えてチェック
                                $items_to_display = $available_inventory[$slot_key] ?? [];
                            ?>
                            <?php if (!empty($items_to_display)): ?>
                                <?php foreach ($items_to_display as $item): ?>
                                    <option value="<?php echo $item['inventory_id']; ?>">
                                        <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>

                        <button type="submit">装備</button>
                        
                        <button type="button" class="compare-button" id="btn-compare-<?php echo $slot_key; ?>" disabled>比較</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <hr>
        <a href="game.php" class="back-link">ゲームに戻る</a>
    </div>

    <div id="compare-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>性能比較</h2>
                <span id="modal-close" class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>ステータス</th>
                            <th id="header-current">現在の装備</th>
                            <th id="header-new">新しい装備</th>
                        </tr>
                    </thead>
                    <tbody id="comparison-body">
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('compare-modal');
        const closeModal = document.getElementById('modal-close');
        const comparisonBody = document.getElementById('comparison-body');
        const headerCurrent = document.getElementById('header-current');
        const headerNew = document.getElementById('header-new');

        // ステータスの表示順とラベル
        const statLabels = {
            'atk': '攻撃力(ATK)',
            'def': '防御力(DEF)',
            'hp_max': '最大HP',
            'strength': '力',
            'vitality': '体力',
            'intelligence': '賢さ',
            'speed': '素早さ',
            'luck': '運',
            'charisma': 'かっこよさ'
        };

        // プルダウンの変更監視
        document.querySelectorAll('.equip-select').forEach(select => {
            select.addEventListener('change', (event) => {
                const slot = event.target.dataset.slot;
                const btn = document.getElementById('btn-compare-' + slot);
                // 何か選択されていれば比較ボタンを有効化
                if (event.target.value) {
                    btn.disabled = false;
                } else {
                    btn.disabled = true;
                }
            });
        });

        // 比較ボタンクリック時の処理
        document.querySelectorAll('.compare-button').forEach(btn => {
            btn.addEventListener('click', async (event) => {
                // ボタンのIDからスロット名を特定 (btn-compare-right_hand 等)
                const slot = event.target.id.replace('btn-compare-', '');
                
                // 1. 現在の装備IDを取得
                const currentSpan = document.getElementById('current-equipped-' + slot);
                const currentId = currentSpan.dataset.inventoryId;

                // 2. 選択された新装備IDを取得
                // 同じ行(form)内のselectを探す
                const form = event.target.closest('form');
                const select = form.querySelector('select[name="inventory_id"]');
                const newId = select.value;

                if (!newId) return; // 念のため

                // 3. データを取得して表示
                await showComparison(currentId, newId);
            });
        });

        // データ取得とモーダル表示を行う関数
        async function showComparison(currentId, newId) {
            
            let currentData = null;
            let newData = null;

            // 新しい装備のデータ取得
            try {
                const resNew = await fetch(`get_item_details.php?inventory_id=${newId}`);
                newData = await resNew.json();
            } catch (e) { console.error(e); return; }

            // 現在の装備のデータ取得 (装備なしの場合はnullのまま)
            if (currentId) {
                try {
                    const resCur = await fetch(`get_item_details.php?inventory_id=${currentId}`);
                    currentData = await resCur.json();
                } catch (e) { console.error(e); }
            }

            // ヘッダー更新
            headerCurrent.textContent = currentData ? currentData.name : '装備なし';
            headerNew.textContent = newData.name;

            // テーブルボディの構築
            comparisonBody.innerHTML = '';

            // 全ステータスについてループ
            Object.keys(statLabels).forEach(key => {
                const label = statLabels[key];
                const valCur = currentData && currentData.stats ? (currentData.stats[key] || 0) : 0;
                const valNew = newData && newData.stats ? (newData.stats[key] || 0) : 0;

                // どちらも0なら表示しない
                if (valCur === 0 && valNew === 0) return;

                const tr = document.createElement('tr');
                
                // ステータス名
                const tdLabel = document.createElement('td');
                tdLabel.className = 'stat-label';
                tdLabel.textContent = label;
                tr.appendChild(tdLabel);

                // 現在の値
                const tdCur = document.createElement('td');
                tdCur.textContent = valCur;
                tr.appendChild(tdCur);

                // 新しい値 (差分表示付き)
                const diff = valNew - valCur;
                const tdNew = document.createElement('td');
                let diffHtml = '';
                if (diff > 0) {
                    diffHtml = ` <span class="diff-positive">(+${diff})</span>`;
                } else if (diff < 0) {
                    diffHtml = ` <span class="diff-negative">(${diff})</span>`;
                } else {
                    diffHtml = ` <span class="diff-zero">(±0)</span>`;
                }
                tdNew.innerHTML = `${valNew}${diffHtml}`;
                tr.appendChild(tdNew);

                comparisonBody.appendChild(tr);
            });

            // 比較項目が一つもない場合
            if (comparisonBody.children.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="3">ステータスの変化はありません</td>';
                comparisonBody.appendChild(tr);
            }

            modal.style.display = 'flex';
        }

        // モーダル閉じる処理
        closeModal.addEventListener('click', () => { modal.style.display = 'none'; });
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
        });
    </script>
</body>
</html>