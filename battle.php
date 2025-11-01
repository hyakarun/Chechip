<?php
session_start();
require_once(__DIR__ . '/db_connect.php');

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['player_id']) || !isset($_POST['dungeon_id'])) {
    header('Location: game.php'); // 行き先が未選択なら追い返す
    exit();
}

$dungeon_id = (int)$_POST['dungeon_id'];

// --- バトル準備 ---
$pdo = connectDb();
$stmt_player = $pdo->prepare("SELECT * FROM players WHERE player_id = :player_id");
$stmt_player->bindValue(':player_id', $_SESSION['player_id'], PDO::PARAM_INT);
$stmt_player->execute();
$player = $stmt_player->fetch();
$initial_player = $player;

// ATK/DEFを算出
$player_atk = $initial_player['strength'];
$player_def = $initial_player['vitality'];

// --- ダンジョンに出現するモンスターをランダムで1体選出 ---
$monsters_in_dungeon_stmt = $pdo->prepare("SELECT monster_id FROM dungeon_monsters WHERE dungeon_id = :dungeon_id");
$monsters_in_dungeon_stmt->bindValue(':dungeon_id', $dungeon_id, PDO::PARAM_INT);
$monsters_in_dungeon_stmt->execute();
$possible_monster_ids = $monsters_in_dungeon_stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($possible_monster_ids)) {
    exit('このダンジョンにはモンスターがいません。');
}

// 出現するモンスターをランダムに決定
$random_key = array_rand($possible_monster_ids);
$selected_monster_id = $possible_monster_ids[$random_key];

// 選ばれたモンスターのステータスを取得
$monster_stmt = $pdo->prepare("SELECT * FROM monsters WHERE id = :monster_id");
$monster_stmt->bindValue(':monster_id', $selected_monster_id, PDO::PARAM_INT);
$monster_stmt->execute();
$monster_data = $monster_stmt->fetch();

$enemy_raw = [
    'name' => $monster_data['name'],
    'hp' => $monster_data['hp'],
    'hp_max' => $monster_data['hp'],
    'str' => $monster_data['strength'],
    'vit' => $monster_data['vitality'],
    'exp' => $monster_data['exp'],
    'gold' => $monster_data['gold'],
    'image' => $monster_data['image']
];

$enemy_atk = $enemy_raw['str'];
$enemy_def = $enemy_raw['vit'];
$enemy = $enemy_raw;
$battle_flow = [];
$turn = 1;

// --- 戦闘ループ ---
$player_current_hp = $initial_player['hp'];
$enemy_current_hp = $enemy['hp'];
while ($player_current_hp > 0 && $enemy_current_hp > 0) {
    // 表示用のHPを更新
    $player['hp'] = $player_current_hp;
    $enemy['hp'] = $enemy_current_hp;
    $battle_flow[] = [ 'type' => 'snapshot', 'turn' => $turn, 'player' => $player, 'enemy' => $enemy ];
    $damage_to_enemy = max(0, $player_atk - $enemy_def);
    $enemy_current_hp -= $damage_to_enemy;
    $battle_flow[] = [ 'type' => 'action', 'actor' => $player, 'text' => $player['name'] . 'は' . $enemy['name'] . 'を攻撃した！ ' . $damage_to_enemy . 'のダメージ。' ];
    if ($enemy_current_hp <= 0) break;
    $damage_to_player = max(0, $enemy_atk - $player_def);
    $player_current_hp -= $damage_to_player;
    $battle_flow[] = [ 'type' => 'action', 'actor' => $enemy, 'text' => $enemy['name'] . 'が反撃！ ' . $player['name'] . 'に' . $damage_to_player . 'のダメージ。' ];
    $turn++;
}

// --- 戦闘終了 ---
$final_player_hp = $player_current_hp;
$final_enemy_hp = $enemy_current_hp;
$final_player_image = $initial_player['avatar_filename'];
$final_enemy_image = $enemy['image'];

if ($player_current_hp > 0) {
    $result_message = "勝利した！";
    $battle_log = [];
    $battle_log[] = $enemy['name'] . 'を倒した！';
    $battle_log[] = $enemy['exp'] . 'の経験値を獲得！';
    $battle_log[] = $enemy['gold'] . 'Gを獲得！';

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

    // 敗北時はHPを1にして保存する（0だと回復できないため）
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
            <a href="game.php">冒険者の家に戻る</a>
        </div>
    </div>
</body>
</html>