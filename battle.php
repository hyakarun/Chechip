<?php
session_start();
require_once(__DIR__ . '/db_connect.php');

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = connectDb(); // 先に接続

// --- ▼▼▼ $floor_id 決定ロジック (10階層対応) ▼▼▼ ---
if (isset($_POST['next_floor_id'])) {
    // 「次の階層へ」ボタンから来た場合
    $floor_id = (int)$_POST['next_floor_id'];

} elseif (isset($_POST['dungeon_id'])) {
    // game.phpのプルダウンから「dungeon_id」が送られてきた場合
    $dungeon_id = (int)$_POST['dungeon_id'];
    
    // DBに接続して、そのダンジョンの「1階層目(floor_number=1)」のfloor_idを特定します
    $stmt_first_floor = $pdo->prepare(
        "SELECT floor_id 
         FROM dungeon_floors
         WHERE dungeon_id = :dungeon_id AND floor_number = 1"
    );
    $stmt_first_floor->bindValue(':dungeon_id', $dungeon_id, PDO::PARAM_INT);
    $stmt_first_floor->execute();
    $target_floor = $stmt_first_floor->fetch();

    if ($target_floor) {
        $floor_id = $target_floor['floor_id'];
    } else {
        exit('ダンジョンの1階層目の特定に失敗しました。');
    }
    
} else {
    header('Location: game.php'); 
    exit();
}
// --- ▲▲▲ ロジック修正ここまで ▲▲▲ ---


// --- バトル準備 ---
$stmt_player = $pdo->prepare("SELECT * FROM players WHERE player_id = :player_id");
$stmt_player->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
$stmt_player->execute();
$player = $stmt_player->fetch();
$initial_player = $player;

// --- レベルに応じた画像を取得 ---
$stmt_avatar = $pdo->prepare(
    "SELECT avatar_filename FROM avatar_growth_table 
     WHERE level <= :player_level 
     ORDER BY level DESC 
     LIMIT 1"
);
$stmt_avatar->bindValue(':player_level', $initial_player['level'], PDO::PARAM_INT);
$stmt_avatar->execute();
$avatar_filename = $stmt_avatar->fetchColumn();
if ($avatar_filename === false) {
    $avatar_filename = 'default_avatar.png';
}
$initial_player['avatar_filename'] = $avatar_filename;
$player['avatar_filename'] = $avatar_filename;

// ATK/DEFを算出 (装備込みのステータス計算)
$base_stats = [
    'hp_max' => $player['hp_max'], 'strength' => $player['strength'], 'vitality' => $player['vitality'],
    'intelligence' => $player['intelligence'], 'speed' => $player['speed'], 'luck' => $player['luck'], 'charisma' => $player['charisma']
];
$bonus_stats = [
    'atk' => 0, 'def' => 0, 'hp_max' => 0, 'strength' => 0, 'vitality' => 0, 
    'intelligence' => 0, 'speed' => 0, 'luck' => 0, 'charisma' => 0
];
$stmt_equipped = $pdo->prepare(
    "SELECT pi.*, i.* FROM player_inventory pi
     JOIN items i ON pi.item_id = i.id
     WHERE pi.player_id = :player_id AND pi.is_equipped = 1"
);
$stmt_equipped->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
$stmt_equipped->execute();
foreach ($stmt_equipped->fetchAll() as $item) {
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

$player_atk = $final_stats['strength'] + $bonus_stats['atk'];
$player_div_def = $bonus_stats['def']; 
$player_sub_def = floor($final_stats['vitality'] / 8); 

// --- 階層に出現するモンスターをランダムで1体選出 ---

// 1. (追加) まず、現在の $floor_id からダンジョンIDを取得します
$dungeon_id_stmt = $pdo->prepare("SELECT dungeon_id FROM dungeon_floors WHERE floor_id = :floor_id");
$dungeon_id_stmt->bindValue(':floor_id', $floor_id, PDO::PARAM_INT);
$dungeon_id_stmt->execute();
$current_dungeon_id = $dungeon_id_stmt->fetchColumn();

if (!$current_dungeon_id) {
    exit('ダンジョンIDの取得に失敗しました。');
}

// 2. (修正) 次に、取得したダンジョンIDを使って floor_monsters を検索します
$monsters_in_floor_stmt = $pdo->prepare("SELECT monster_id FROM floor_monsters WHERE dungeon_id = :dungeon_id");
$monsters_in_floor_stmt->bindValue(':dungeon_id', $current_dungeon_id, PDO::PARAM_INT);
$monsters_in_floor_stmt->execute();
$possible_monster_ids = $monsters_in_floor_stmt->fetchAll(PDO::FETCH_COLUMN);
// (以下略)

if (empty($possible_monster_ids)) {
    exit('この階層にはモンスターがいません。');
}
$random_key = array_rand($possible_monster_ids);
$selected_monster_id = $possible_monster_ids[$random_key];
$monster_stmt = $pdo->prepare("SELECT * FROM monsters WHERE id = :id");
$monster_stmt->bindValue(':id', $selected_monster_id, PDO::PARAM_INT);
$monster_stmt->execute();
$monster_data = $monster_stmt->fetch();


// --- ▼▼▼ モンスター情報取得 (最重要) ▼▼▼ ---
$enemy = [
    'id' => $monster_data['id'],
    'name' => $monster_data['name'],
    'hp' => $monster_data['hp'],
    'hp_max' => $monster_data['hp'], 
    'exp' => $monster_data['exp'],
    'gold' => $monster_data['gold'],
    'image' => $monster_data['image']
];
$enemy_atk = $monster_data['strength'] + $monster_data['atk'];
$enemy_def = $monster_data['def']; 

// --- ▲▲▲ モンスター情報取得ここまで ▲▲▲ ---



$battle_flow = [];
$turn = 1;

// --- 戦闘ループ ---
$player_current_hp = $initial_player['hp'];
$enemy_current_hp = $enemy['hp'];
while ($player_current_hp > 0 && $enemy_current_hp > 0) {
    $player['hp'] = $player_current_hp;
    $enemy['hp'] = $enemy_current_hp;
    $battle_flow[] = [ 'type' => 'snapshot', 'turn' => $turn, 'player' => $player, 'enemy' => $enemy ];

    // プレイヤーの攻撃
    // (★ 計算式をプレイヤーへのダメージ計算式と統一)
    $enemy_div_def = $enemy_def; 
    $enemy_sub_def = floor($monster_data['vitality'] / 8); 

    // (★ ユーザー指定の計算式: (ATK/2 * (100 - (除算DEFの立方根))% ) - 減算DEF)
    $base_atk_half = $player_atk / 2;
    // (除算DEFが100%を超えてダメージがマイナスにならないよう max(0, ...) で調整)
    // ▼▼▼ 立方根を適用 ▼▼▼
    $defense_multiplier = max(0, (100 - pow($enemy_div_def, 1/3))) / 100; 
    // ▲▲▲ 変更点 ▲▲▲
    $calculated_damage = ($base_atk_half * $defense_multiplier) - $enemy_sub_def;
    $random_factor_enemy = mt_rand(90, 110) / 100;
    $randomized_damage_enemy = $calculated_damage * $random_factor_enemy;

    if ($player_atk > 0) {
        $damage_to_enemy = max(1, floor($randomized_damage_enemy));
    } else {
        $damage_to_enemy = 0;
    }
    $damage_to_enemy = min($damage_to_enemy, 9999); 

    $enemy_current_hp -= $damage_to_enemy;
    $battle_flow[] = [ 'type' => 'action', 'actor' => $player, 'text' => $player['name'] . 'は' . $enemy['name'] . 'を攻撃した！ ' . $damage_to_enemy . 'のダメージ。' ];
    
    if ($enemy_current_hp <= 0) break; 

    // モンスターの反撃
    // (★ ユーザー指定の計算式: (ATK/2 * (100 - (除算DEFの立方根))% ) - 減算DEF)
    $base_atk_half = $enemy_atk / 2;
    // (除算DEFが100%を超えてダメージがマイナスにならないよう max(0, ...) で調整)
    // ▼▼▼ 立方根を適用 ▼▼▼
    $defense_multiplier = max(0, (100 - pow($player_div_def, 1/3))) / 100;
    // ▲▲▲ 変更点 ▲▲▲
    $calculated_damage = ($base_atk_half * $defense_multiplier) - $player_sub_def;
    $random_factor_player = mt_rand(90, 110) / 100;
    $randomized_damage_player = $calculated_damage * $random_factor_player;

    if ($enemy_atk > 0) {
        $damage_to_player = max(1, floor($randomized_damage_player));
    } else {
        $damage_to_player = 0;
    }
    $damage_to_player = min($damage_to_player, 9999);

    $player_current_hp -= $damage_to_player;
    $battle_flow[] = [ 'type' => 'action', 'actor' => $enemy, 'text' => $enemy['name'] . 'が反撃！ ' . $player['name'] . 'に' . $damage_to_player . 'のダメージ。' ];
    
    $turn++;
}

// --- 戦闘終了 ---
$final_player_hp = $player_current_hp;
$final_enemy_hp = $enemy_current_hp;
$final_player_image = $initial_player['avatar_filename'];
$final_enemy_image = $enemy['image'];

$next_floor_info = null; 
$battle_log = []; 

if ($player_current_hp > 0) {
    $result_message = "勝利した！";
    // --- ▼▼▼ 経験値・ゴールド表示 (最重要) ▼▼▼ ---
    $battle_log[] = $enemy['name'] . 'を倒した！';
    $battle_log[] = $enemy['exp'] . 'の経験値を獲得！';
    $battle_log[] = $enemy['gold'] . 'Gを獲得！';
    // --- ▲▲▲ ここまで ▲▲▲ ---

    // --- 階層クリア処理 (10階層対応) ---
    $floor_info_stmt = $pdo->prepare("SELECT * FROM dungeon_floors WHERE floor_id = :floor_id");
    $floor_info_stmt->bindValue(':floor_id', $floor_id, PDO::PARAM_INT);
    $floor_info_stmt->execute();
    $floor_info = $floor_info_stmt->fetch();
    
    $reward_item_id = $floor_info['completion_reward_item_id'];
    $reward_gold = $floor_info['completion_reward_gold'];
    $unlocked_dungeon_id = $floor_info['unlocks_dungeon_id']; 
    $current_dungeon_id = $floor_info['dungeon_id'];
    $current_floor_number = $floor_info['floor_number'];
    
    $progress_stmt = $pdo->prepare("SELECT highest_floor FROM player_progress WHERE player_id = :player_id AND dungeon_id = :dungeon_id");
    $progress_stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $progress_stmt->bindValue(':dungeon_id', $current_dungeon_id, PDO::PARAM_INT);
    $progress_stmt->execute();
    $highest_floor = $progress_stmt->fetchColumn();

    $next_floor_number = $current_floor_number + 1;
    $next_floor_stmt = $pdo->prepare(
        "SELECT f.floor_id, d.name 
         FROM dungeon_floors f 
         JOIN dungeons d ON f.dungeon_id = d.id 
         WHERE f.dungeon_id = :dungeon_id AND f.floor_number = :next_floor"
    );
    $next_floor_stmt->bindValue(':dungeon_id', $current_dungeon_id, PDO::PARAM_INT);
    $next_floor_stmt->bindValue(':next_floor', $next_floor_number, PDO::PARAM_INT);
    $next_floor_stmt->execute();
    $next_floor_info = $next_floor_stmt->fetch(); 

    if ($highest_floor == $current_floor_number) {
        
        if ($next_floor_info) {
            // --- 次の階層がある場合 (例: 1F → 2F) ---
            $update_progress_stmt = $pdo->prepare(
                "UPDATE player_progress SET highest_floor = :next_floor 
                 WHERE player_id = :player_id AND dungeon_id = :dungeon_id"
            );
            $update_progress_stmt->bindValue(':next_floor', $next_floor_number, PDO::PARAM_INT);
            $update_progress_stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
            $update_progress_stmt->bindValue(':dungeon_id', $current_dungeon_id, PDO::PARAM_INT);
            $update_progress_stmt->execute();
            
            $battle_log[] = '--------------------------------';
            $battle_log[] = '「' . htmlspecialchars($next_floor_info['name'], ENT_QUOTES, 'UTF-8') . ' ' . $next_floor_number . 'F」が解放された！';
            $battle_log[] = '--------------------------------';
        
        } elseif ($unlocked_dungeon_id) {
            // --- 最終階層 (10F) クリアで、次のダンジョンが解放される場合 ---
            $check_next_dungeon_stmt = $pdo->prepare("SELECT 1 FROM player_progress WHERE player_id = :player_id AND dungeon_id = :dungeon_id");
            $check_next_dungeon_stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
            $check_next_dungeon_stmt->bindValue(':dungeon_id', $unlocked_dungeon_id, PDO::PARAM_INT);
            $check_next_dungeon_stmt->execute();
            
            if (!$check_next_dungeon_stmt->fetch()) {
                $unlock_stmt = $pdo->prepare("INSERT INTO player_progress (player_id, dungeon_id, highest_floor) VALUES (:player_id, :dungeon_id, 1)");
                $unlock_stmt->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
                $unlock_stmt->bindValue(':dungeon_id', $unlocked_dungeon_id, PDO::PARAM_INT);
                $unlock_stmt->execute();

                $next_dungeon_name_stmt = $pdo->prepare("SELECT name FROM dungeons WHERE id = :id");
                $next_dungeon_name_stmt->bindValue(':id', $unlocked_dungeon_id, PDO::PARAM_INT);
                $next_dungeon_name_stmt->execute();
                $next_dungeon_name = $next_dungeon_name_stmt->fetchColumn();

                $battle_log[] = '--------------------------------';
                $battle_log[] = 'ダンジョンクリア！';
                $battle_log[] = '新しいダンジョン「' . htmlspecialchars($next_dungeon_name, ENT_QUOTES, 'UTF-8') . '」が解放された！';
                $battle_log[] = '--------------------------------';
            }
        }
    }

    // --- アイテムドロップ処理 ---
    $drop_stmt = $pdo->prepare("SELECT * FROM monster_drops WHERE monster_id = :monster_id");
    $drop_stmt->bindValue(':monster_id', $monster_data['id'], PDO::PARAM_INT);
    $drop_stmt->execute();
    $possible_drops = $drop_stmt->fetchAll();
    foreach ($possible_drops as $drop) {
        $roll = rand(1, 100);
        if ($roll <= ($drop['drop_chance'] * 100)) {
            $item_id = $drop['item_id'];
            $item_template_stmt = $pdo->prepare("SELECT * FROM items WHERE id = :item_id");
            $item_template_stmt->bindValue(':item_id', $item_id, PDO::PARAM_INT);
            $item_template_stmt->execute();
            $item_template = $item_template_stmt->fetch();
            if ($item_template) {
                $options_to_generate = rand($item_template['random_option_min_count'], $item_template['random_option_max_count']);
                $generated_options = [];
                $possible_stats = ['hp_max', 'strength', 'vitality', 'intelligence', 'speed', 'luck', 'charisma', 'atk', 'def'];
                for ($i = 0; $i < $options_to_generate; $i++) {
                    $random_stat_key = array_rand($possible_stats);
                    $stat_name = $possible_stats[$random_stat_key];
                    $stat_value = rand(1, 5);
                    $generated_options[] = ['stat' => $stat_name, 'value' => $stat_value];
                }
                $sql = "INSERT INTO player_inventory (player_id, item_id, option_1_stat, option_1_value, option_2_stat, option_2_value, option_3_stat, option_3_value, option_4_stat, option_4_value, option_5_stat, option_5_value) 
                        VALUES (:player_id, :item_id, :o1s, :o1v, :o2s, :o2v, :o3s, :o3v, :o4s, :o4v, :o5s, :o5v)";
                $add_item_stmt = $pdo->prepare($sql);
                $params = [
                    ':player_id' => $_SESSION['player_id'], ':item_id' => $item_id,
                    ':o1s' => $generated_options[0]['stat'] ?? NULL, ':o1v' => $generated_options[0]['value'] ?? NULL,
                    ':o2s' => $generated_options[1]['stat'] ?? NULL, ':o2v' => $generated_options[1]['value'] ?? NULL,
                    ':o3s' => $generated_options[2]['stat'] ?? NULL, ':o3v' => $generated_options[2]['value'] ?? NULL,
                    ':o4s' => $generated_options[3]['stat'] ?? NULL, ':o4v' => $generated_options[3]['value'] ?? NULL,
                    ':o5s' => $generated_options[4]['stat'] ?? NULL, ':o5v' => $generated_options[4]['value'] ?? NULL,
                ];
                $add_item_stmt->execute($params);
                $battle_log[] = $item_template['name'] . 'を手に入れた！';
            }
        }
    }
    
    // --- 報酬とレベルアップ処理 ---
    $new_exp = $initial_player['exp'] + $enemy['exp'];
    $new_gold = $initial_player['gold'] + $enemy['gold'];
    $stmt_exp = $pdo->prepare("SELECT required_exp FROM experience_table WHERE level = :next_level");
    $stmt_exp->bindValue(':next_level', $initial_player['level'] + 1, PDO::PARAM_INT);
    $stmt_exp->execute();
    $next_level_info = $stmt_exp->fetch();

    if ($next_level_info && $new_exp >= $next_level_info['required_exp']) {
        $new_level = $initial_player['level'] + 1;
        $stmt_status = $pdo->prepare("SELECT * FROM status_growth_table WHERE level = :new_level");
        $stmt_status->bindValue(':new_level', $new_level, PDO::PARAM_INT);
        $stmt_status->execute();
        $new_stats = $stmt_status->fetch();
        if ($new_stats) {
            $final_hp_after_levelup = $new_stats['hp_max']; 
            $stmt_update = $pdo->prepare(
                "UPDATE players SET level = :level, exp = :exp, gold = :gold, hp = :hp, hp_max = :hp_max, 
                    strength = :strength, vitality = :vitality, intelligence = :intelligence, 
                    speed = :speed, luck = :luck, charisma = :charisma
                WHERE player_id = :player_id"
            );
            $stmt_update->bindValue(':level', $new_level, PDO::PARAM_INT);
            $stmt_update->bindValue(':hp', $final_hp_after_levelup, PDO::PARAM_INT);
            $stmt_update->bindValue(':hp_max', $new_stats['hp_max'], PDO::PARAM_INT);
            $stmt_update->bindValue(':strength', $new_stats['strength'], PDO::PARAM_INT);
            $stmt_update->bindValue(':vitality', $new_stats['vitality'], PDO::PARAM_INT);
            $stmt_update->bindValue(':intelligence', $new_stats['intelligence'], PDO::PARAM_INT);
            $stmt_update->bindValue(':speed', $new_stats['speed'], PDO::PARAM_INT);
            $stmt_update->bindValue(':luck', $new_stats['luck'], PDO::PARAM_INT);
            $stmt_update->bindValue(':charisma', $new_stats['charisma'], PDO::PARAM_INT);
            $battle_log[] = '--------------------------------';
            $battle_log[] = 'レベルアップ！ レベル' . $new_level . 'になった！';
            $battle_log[] = 'ステータスが成長した！';
            $battle_log[] = '--------------------------------';
            $final_player_hp = $final_hp_after_levelup;
        } else {
            $stmt_update = $pdo->prepare("UPDATE players SET exp = :exp, gold = :gold, hp = :hp WHERE player_id = :player_id");
            $stmt_update->bindValue(':hp', $player_current_hp, PDO::PARAM_INT);
        }
    } else {
        $stmt_update = $pdo->prepare("UPDATE players SET exp = :exp, gold = :gold, hp = :hp WHERE player_id = :player_id");
        $stmt_update->bindValue(':hp', $player_current_hp, PDO::PARAM_INT);
    }
    $stmt_update->bindValue(':exp', $new_exp, PDO::PARAM_INT);
    $stmt_update->bindValue(':gold', $new_gold, PDO::PARAM_INT);
    $stmt_update->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $stmt_update->execute();

} else {
    $result_message = "敗北した...";
    $stmt_defeat = $pdo->prepare("UPDATE players SET hp = 1 WHERE player_id = :player_id");
    $stmt_defeat->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
    $stmt_defeat->execute();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>戦闘ログ</title>
    <style>
        body { background-color: #333; color: #eee; font-family: sans-serif; }
        .battle-log-page { max-width: 800px; margin: 20px auto; }
        h1, h2, h3 { text-align: center; }
        .turn-header { background-color: #444; padding: 5px; text-align: center; font-weight: bold; margin-top: 30px; }
        .battle-snapshot { display: flex; justify-content: space-between; padding: 15px; background-color: #2e2e2e; }
        .party-area { width: 48%; }
        .vs-text { align-self: center; font-weight: bold; }
        .character-info { display: flex; align-items: center; margin-bottom: 10px; }
        .character-info img { width: 48px; height: 48px; margin-right: 10px; background-color: #fff; border: 1px solid #555; }
        .stats { flex-grow: 1; }
        .stats p { margin: 0 0 5px; font-size: 0.9em; }
        .hp-bar-outer { width: 100%; height: 15px; background-color: #111; border: 1px solid #555; }
        .hp-bar-inner { height: 100%; background-color: #32a852; }
        .action-log { display: flex; align-items: center; padding: 15px; border-top: 1px solid #444; }
        .action-log img { width: 48px; height: 48px; margin-right: 15px; }
        .action-log p { margin: 0; }
        .result-area { text-align: center; margin-top: 30px; }
        a { color: #8af; }
        .battle-final-snapshot { display: flex; justify-content: space-around; padding: 20px; margin-bottom: 20px; background-color: #2e2e2e; border: 1px solid #555; align-items: flex-start; }
        .battle-final-snapshot .character-info { flex-direction: column; align-items: center; }
        .battle-final-snapshot .character-info img { width: 96px; height: 96px; margin-bottom: 10px; object-fit: contain; }
        .battle-final-snapshot .character-info .stats { text-align: center; }
        .battle-final-snapshot .character-info p { font-size: 1em; margin: 5px 0; }
        .battle-final-snapshot .vs-text { font-size: 1.5em; align-self: center; padding: 0 20px; }
        .dead-character-image { filter: grayscale(100%) brightness(50%); opacity: 0.7; }
        .result-area form { display: inline; }
        .result-area button { padding: 10px 20px; font-size: 1em; }
    </style>
</head>
<body>
    <div class="battle-log-page">
        <h1>戦闘ログ</h1>

        <?php foreach ($battle_flow as $entry): ?>
            <?php if ($entry['type'] === 'snapshot'): ?>
                <div class="turn-header"><?php echo $entry['turn']; ?>ターン</div>
                <div class="battle-snapshot">
                    <div class="party-area">
                        <?php $p = $entry['player']; $hp_percent = ($p['hp'] > 0 ? $p['hp'] / $initial_player['hp_max'] : 0) * 100; ?>
                        <div class="character-info">
                            <img src="images/<?php echo htmlspecialchars($initial_player['avatar_filename'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $p['name']; ?>">
                            <div class="stats">
                                <p><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="hp-bar-outer"><div class="hp-bar-inner" style="width: <?php echo $hp_percent; ?>%;"></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="vs-text">VS</div>
                    <div class="party-area">
                        <?php $e = $entry['enemy']; $hp_percent_e = ($e['hp'] > 0 ? $e['hp'] / $e['hp_max'] : 0) * 100; ?>
                        <div class="character-info">
                             <img src="images/<?php echo htmlspecialchars($e['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $e['name']; ?>">
                            <div class="stats">
                                <p><?php echo htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="hp-bar-outer"><div class="hp-bar-inner" style="width: <?php echo $hp_percent_e; ?>%;"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($entry['type'] === 'action'): ?>
                <div class="action-log">
                    <img src="images/<?php echo htmlspecialchars($entry['actor']['avatar_filename'] ?? $entry['actor']['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                    <p><?php echo htmlspecialchars($entry['text'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <div class="result-area">
            <h2><?php echo $result_message; ?></h2>

            <div class="battle-final-snapshot">
                <div class="character-info">
                    <?php $player_image_class = ($final_player_hp <= 0) ? ' dead-character-image' : ''; ?>
                    <img class="<?php echo trim($player_image_class); ?>" src="images/<?php echo htmlspecialchars($final_player_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $initial_player['name']; ?>">
                    <div class="stats">
                        <p><?php echo htmlspecialchars($initial_player['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php $player_final_hp_percent = ($final_player_hp > 0 ? $final_player_hp / $initial_player['hp_max'] : 0) * 100; ?>
                        <div class="hp-bar-outer"><div class="hp-bar-inner" style="width: <?php echo $player_final_hp_percent; ?>%;"></div></div>
                        <p>HP: <?php echo ($final_player_hp > 0 ? $final_player_hp : 0); ?> / <?php echo $initial_player['hp_max']; ?></p>
                    </div>
                </div>
                <div class="vs-text">VS</div>
                <div class="character-info">
                    <?php 
                        $enemy_final_hp_percent = ($final_enemy_hp > 0 ? $final_enemy_hp / $enemy['hp_max'] : 0) * 100;
                        $enemy_image_class = ($final_enemy_hp <= 0 && $player_current_hp > 0) ? ' dead-character-image' : ''; 
                    ?>
                    <img class="<?php echo trim($enemy_image_class); ?>" src="images/<?php echo htmlspecialchars($final_enemy_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($enemy['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="stats">
                        <p><?php echo htmlspecialchars($enemy['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="hp-bar-outer"><div class="hp-bar-inner" style="width: <?php echo $enemy_final_hp_percent; ?>%;"></div></div>
                        <p>HP: <?php echo ($final_enemy_hp > 0 ? $final_enemy_hp : 0); ?> / <?php echo $enemy['hp_max']; ?></p>
                    </div>
                </div>
            </div>

            <?php foreach ($battle_log as $log): ?>
                <p><?php echo htmlspecialchars($log, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endforeach; ?>
            
            <hr>
            
            <?php if ($player_current_hp > 0 && $next_floor_info): ?>
                
                <form action="battle.php" method="post">
                    <input type="hidden" name="next_floor_id" value="<?php echo $next_floor_info['floor_id']; ?>">
                    <button type="submit">次の階層へ (<?php echo htmlspecialchars($next_floor_info['name'], ENT_QUOTES, 'UTF-8') . ' ' . ($current_floor_number + 1) . 'F'; ?>)</button>
                </form>
                
                <a href="game.php"><button type="button">ホームに戻る</button></a> 
            <?php else: ?>
                <a href="game.php"><button type="button">ホームに戻る</button></a> 
            <?php endif; ?>
            
        </div>
    </div>
</body>
</html>