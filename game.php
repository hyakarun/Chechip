<?php
session_start();

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

require_once(__DIR__ . '/db_connect.php');
try {
    $pdo = connectDb();
    $stmt = $pdo->prepare("SELECT * FROM players WHERE player_id = :player_id");
    $stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $stmt->execute();
    $player = $stmt->fetch();

    if (!$player) { exit('プレイヤー情報が取得できませんでした。'); }

    // --- 装備スロットの定義 ---
    $all_slots = [
        'right_hand' => '右手', 'left_hand' => '左手', 'head_top' => '頭上段', 'head_middle' => '頭中断',
        'head_bottom' => '頭下段', 'neck' => '首', 'body' => '体', 'arm' => '腕', 'waist' => '腰',
        'leg' => '足', 'foot' => '靴', 'accessory1' => 'アクセサリー1', 'accessory2' => 'アクセサリー2'
    ];

    // --- ステータス計算と装備表示の準備 ---
    $base_stats = [
        'hp_max' => $player['hp_max'], 'strength' => $player['strength'], 'vitality' => $player['vitality'],
        'intelligence' => $player['intelligence'], 'speed' => $player['speed'], 'luck' => $player['luck'], 'charisma' => $player['charisma']
    ];
    $bonus_stats = [
        'atk' => 0, 'def' => 0, 'hp_max' => 0, 'strength' => 0, 'vitality' => 0, 
        'intelligence' => 0, 'speed' => 0, 'luck' => 0, 'charisma' => 0
    ];
    $equipped_display = [];
    foreach ($all_slots as $slot_key => $slot_name) {
        $equipped_display[$slot_key] = 'なし';
    }
    $stmt_equipped = $pdo->prepare(
        "SELECT pi.*, i.* FROM player_inventory pi
         JOIN items i ON pi.item_id = i.id
         WHERE pi.player_id = :player_id AND pi.is_equipped = 1"
    );
    $stmt_equipped->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $stmt_equipped->execute();
    foreach ($stmt_equipped->fetchAll() as $item) {
        $slot = $item['equip_slot'];
        if (isset($equipped_display[$slot])) {
            $equipped_display[$slot] = $item['name'];
        }
        $bonus_stats['atk'] += $item['base_atk'];
        $bonus_stats['def'] += $item['base_def'];
        $bonus_stats['hp_max'] += $item['guaranteed_hp_max'];
        $bonus_stats['strength'] += $item['guaranteed_strength'];
        $bonus_stats['vitality'] += $item['guaranteed_vitality'];
        $bonus_stats['intelligence'] += $item['guaranteed_intelligence'];
        $bonus_stats['speed'] += $item['guaranteed_speed'];
        $bonus_stats['luck'] += $item['guaranteed_luck'];
        $bonus_stats['charisma'] += $item['guaranteed_charisma'];
        for ($i = 1; $i <= 5; $i++) {
            $stat_name = $item['option_' . $i . '_stat'];
            $stat_value = $item['option_' . $i . '_value'];
            if ($stat_name && isset($bonus_stats[$stat_name])) {
                $bonus_stats[$stat_name] += $stat_value;
            }
        }
    }
    $final_stats = $base_stats;
    foreach ($bonus_stats as $key => $value) {
        if (isset($final_stats[$key])) {
            $final_stats[$key] += $value;
        }
    }
    $player['hp_max'] = $final_stats['hp_max'];
    $player['strength'] = $final_stats['strength'];
    $player['vitality'] = $final_stats['vitality'];
    $player['intelligence'] = $final_stats['intelligence'];
    $player['speed'] = $final_stats['speed'];
    $player['luck'] = $final_stats['luck'];
    $player['charisma'] = $final_stats['charisma'];
    $atk = floor($player['strength'] / 4) + $bonus_stats['atk']; 
    $div_def = $bonus_stats['def']; 
    $sub_def = floor($player['vitality'] / 8); 

    // (オフライン回復処理)
    $current_time = new DateTime();
    $last_update_time = new DateTime($player['last_updated_at']);
    $elapsed_seconds = $current_time->getTimestamp() - $last_update_time->getTimestamp();
    $elapsed_minutes = floor($elapsed_seconds / 60);
    if ($elapsed_minutes > 0 && $player['hp'] < $player['hp_max'] && $player['hp'] > 1) {
        $recover_per_minute = max(1, floor($player['hp_max'] * 0.01));
        $total_recovery = min($player['hp_max'], $player['hp'] + ($recover_per_minute * $elapsed_minutes));
        $stmt_hp = $pdo->prepare("UPDATE players SET hp = :hp WHERE player_id = :player_id");
        $stmt_hp->bindValue(':hp', $total_recovery, PDO::PARAM_INT);
        $stmt_hp->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
        $stmt_hp->execute();
        $player['hp'] = $total_recovery;
    }

    // --- 次のレベルに必要な経験値を取得 ---
    $stmt_exp = $pdo->prepare("SELECT required_exp FROM experience_table WHERE level = :next_level");
    $stmt_exp->bindValue(':next_level', $player['level'] + 1, PDO::PARAM_INT);
    $stmt_exp->execute();
    $next_level_exp = $stmt_exp->fetchColumn();
    if ($next_level_exp === false) { $next_level_exp = 'MAX'; }
    
    // --- ▼▼▼ ダンジョンリスト取得ロジック (修正) ▼▼▼ ---
    // プレイヤーが解放している「ダンジョン名」と「そのダンジョンの最高到達階」を取得
    $dungeons_stmt = $pdo->prepare(
        "SELECT d.id, d.name, p.highest_floor
         FROM dungeons d
         JOIN player_progress p ON d.id = p.dungeon_id
         WHERE p.player_id = :player_id
         ORDER BY d.id"
    );
    $dungeons_stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $dungeons_stmt->execute();
    $dungeons = $dungeons_stmt->fetchAll();
    // --- ▲▲▲ ロジック修正ここまで ▲▲▲ ---

    // (L120)
    $dungeons = $dungeons_stmt->fetchAll();
    // --- ▲▲▲ ロジック修正ここまで ▲▲▲


    // --- ▼▼▼ (ここから追記) 新規ユーザーでダンジョンが空だった場合の処理 ▼▼▼ ---
    if (empty($dungeons)) {
        // player_progress にデータが1件もないか確認
        $stmt_check = $pdo->prepare("SELECT 1 FROM player_progress WHERE player_id = :player_id LIMIT 1");
        $stmt_check->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
        $stmt_check->execute();
        
        if ($stmt_check->fetchColumn() === false) {
            // 本当に1件もデータがない場合のみ、ダンジョンID:1の1Fを付与
            // (注: データベースに dungeon_id: 1 が存在することが前提です)
            $stmt_insert = $pdo->prepare(
                "INSERT INTO player_progress (player_id, dungeon_id, highest_floor) 
                 VALUES (:player_id, 1, 1)"
            );
            $stmt_insert->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
            $stmt_insert->execute();
            
            // ダンジョンリストを再取得
            $dungeons_stmt->execute();
            $dungeons = $dungeons_stmt->fetchAll();
        }
    }
    // --- ▲▲▲ (追記ここまで) ▲▲▲ ---


    // --- レベルに応じた画像を取得 ---
    $stmt_avatar = $pdo->prepare(
        "SELECT avatar_filename FROM avatar_growth_table 
         WHERE level <= :player_level 
         ORDER BY level DESC 
         LIMIT 1"
    );
    $stmt_avatar->bindValue(':player_level', $player['level'], PDO::PARAM_INT);
    $stmt_avatar->execute();
    $avatar_filename = $stmt_avatar->fetchColumn();
    if ($avatar_filename === false) {
        $avatar_filename = 'default_avatar.png';
    }

} catch (PDOException $e) { exit('データベースエラー: ' . $e->getMessage()); }

foreach ($player as $key => $value) { $player[$key] = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ちぇいぶちぷたー</title>
    <style>
        body { background-color: #333; color: #eee; font-family: sans-serif; display: flex; }
        .main-content { flex: 2; padding: 20px; }
        .sidebar { flex: 1; padding: 20px; background-color: #444; border-left: 1px solid #666; }
        h2 { border-bottom: 1px solid #555; padding-bottom: 10px; }
        .profile-summary-container { display: flex; align-items: center; gap: 20px; margin-bottom: 0px; }
        .player-profile img { width: 128px; height: 128px; border: 2px solid #555; background-color: #ffffff; }
        /* ▼▼▼ スプライトアニメーション用CSS ▼▼▼ */
        #player-avatar {
            width: 128px; 
            height: 128px;
            background-image: url('images/<?php echo htmlspecialchars($avatar_filename, ENT_QUOTES, 'UTF-8'); ?>');
            animation: avatar-animation 1s steps(4) infinite;
        }
        @keyframes avatar-animation {
            from { background-position: 0px 0; }
            to { background-position: -512px 0; }
        }
        /* ▲▲▲ アニメーションCSSここまで ▲▲▲ */
        .player-summary { flex: 1; }
        .player-summary h2 { margin-top: 0; }
        .player-summary p { margin: 8px 0; }
        .hp-display { margin: 8px 0; }
        .hp-bar-outer { width: 100%; max-width: 300px; height: 15px; background-color: #111; border: 1px solid #555; border-radius: 4px; margin-top: 4px; }
        .hp-bar-inner { height: 100%; background-color: #32a852; border-radius: 3px; transition: width 0.5s; }
        .top-container { display: flex; gap: 20px; margin-bottom: 0px; }
        .status-box, .equipment-box { flex: 1; background-color: #282828; border: 1px solid #555; padding: 15px; }
        .skill-box { background-color: #282828; border: 1px solid #555; padding: 15px; }
        .status-list p, .equipment-list p { margin: 8px 0; }
        .sidebar a { display: block; margin-bottom: 10px; color: #8af; }
        .adventure-form select, .adventure-form button { width: 100%; padding: 8px; margin-bottom: 10px; background-color: #555; color: #eee; border: 1px solid #666; border-radius: 4px; }
        .adventure-form button { cursor: pointer; font-weight: bold; }
        .adventure-form button:hover { background-color: #666; }
        .box-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #555; padding-bottom: 10px; }
        .box-header h2 { border-bottom: none; padding-bottom: 0; margin: 0; }
        .header-button { display: inline-block; background-color: #555; color: #eee; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.9em; }
        .header-button:hover { background-color: #666; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="profile-summary-container">
            <div class="player-profile">
                <div id="player-avatar"></div>
            </div>
            <div class="player-summary">
                <h2><?php echo $player['name']; ?></h2>
                <p>
                    <span>LV: <?php echo $player['level']; ?></span> | 
                    <span>経験値: <?php echo $player['exp']; ?> / <?php echo $next_level_exp; ?></span>
                </p>
                <div class="hp-display">
                     <span>HP: <span id="current-hp"><?php echo $player['hp']; ?></span> / <span id="max-hp"><?php echo $player['hp_max']; ?></span></span>
                     <div class="hp-bar-outer">
                        <?php $hp_percent = ($player['hp_max'] > 0) ? ($player['hp'] / $player['hp_max']) * 100 : 0; ?>
                        <div id="hp-bar-inner" class="hp-bar-inner" style="width: <?php echo $hp_percent; ?>%;"></div>
                     </div>
                </div>
                <p>所持金: <?php echo $player['gold']; ?>G</p>
            </div>
        </div>
        
        <div class="top-container">
            <div class="status-box">
                <h2>ステータス</h2>
                <div class="status-list">
                    <p>攻撃力: <?php echo $atk; ?></p>
                    <p>防御力: <?php echo $div_def; ?> + <?php echo $sub_def; ?></p>
                    <p>力: <?php echo $player['strength']; ?></p>
                    <p>体力: <?php echo $player['vitality']; ?></p>
                    <p>賢さ: <?php echo $player['intelligence']; ?></p>
                    <p>素早さ: <?php echo $player['speed']; ?></p>
                    <p>運の良さ: <?php echo $player['luck']; ?></p>
                    <p>かっこよさ: <?php echo $player['charisma']; ?></p>
                </div>
            </div>

            <div class="equipment-box">
                <div class="box-header"> 
                    <h2>装備</h2>
                    <a href="equipment.php" class="header-button">装備変更</a>
                </div>
                <div class="equipment-list">
                    <?php foreach ($equipped_display as $slot_key => $item_name): ?>
                        <p><?php echo htmlspecialchars($all_slots[$slot_key], ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="skill-box">
            <h2>スキル</h2>
            <p>アクティブ: なし</p>
            <p>パッシブ: なし</p>
        </div>
    </div>

    <div class="sidebar">
        <h2>コンテンツ</h2>
        <form action="battle.php" method="post" class="adventure-form">
            <select name="dungeon_id" required> 
                <option value="" disabled selected>行き先を選択...</option>
                <?php foreach ($dungeons as $dungeon): ?>
                    <option value="<?php echo $dungeon['id']; ?>">
                        <?php echo htmlspecialchars($dungeon['name'], ENT_QUOTES, 'UTF-8'); ?> 
                        (現在 <?php echo htmlspecialchars($dungeon['highest_floor'], ENT_QUOTES, 'UTF-8'); ?>F)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">冒険へ</button>
        </form>
        <hr>
        <a href="shop.php">道具屋</a>
        <a href="inventory.php">アイテム</a>
        <a href="news.php">お知らせ</a>
        <a href="#" id="inn-button">宿屋</a>
        <hr>
        <a href="logout.php">ログアウト</a>
    </div>

    <script>
        const currentHpEl = document.getElementById('current-hp');
        const maxHpEl = document.getElementById('max-hp');
        const hpBarInner = document.getElementById('hp-bar-inner');

        setInterval(() => {
            let currentHp = parseInt(currentHpEl.textContent);
            const maxHp = parseInt(maxHpEl.textContent);
            if (currentHp >= maxHp) return;
            let recoveryAmount = Math.max(1, Math.floor(maxHp * 0.01));
            currentHp = Math.min(maxHp, currentHp + recoveryAmount);
            currentHpEl.textContent = currentHp;
            const newWidth = (currentHp / maxHp) * 100;
            hpBarInner.style.width = newWidth + '%';
            const formData = new FormData();
            formData.append('hp', currentHp);
            fetch('update_hp.php', { method: 'POST', body: formData })
                .catch(error => console.error('HPの保存に失敗:', error));
        }, 60000); 

        const innButton = document.getElementById('inn-button');
        innButton.addEventListener('click', (event) => {
            event.preventDefault();
            const playerLevel = <?php echo $player['level']; ?>;
            const cost = playerLevel * 1000;
            const confirmed = confirm('宿屋に泊まりますか？\n料金は ' + cost + 'G です。');
            if (confirmed) {
                window.location.href = 'inn.php';
            }
        });
    </script>
</body>
</html>