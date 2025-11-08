<?php
session_start();
if (!isset($_SESSION['player_id']) || !isset($_GET['inventory_id'])) {
    http_response_code(400); // 不正なリクエスト
    exit();
}

require_once(__DIR__ . '/db_connect.php');

$player_id = $_SESSION['player_id'];
$inventory_id = (int)$_GET['inventory_id'];

$details = [];

try {
    $pdo = connectDb();
    
    // プレイヤーが本当にそのアイテムを持っているか確認しつつ、全情報を取得
    $stmt = $pdo->prepare(
        "SELECT pi.*, i.name, i.base_atk, i.base_def, i.guaranteed_hp_max, 
                i.guaranteed_strength, i.guaranteed_vitality, i.guaranteed_intelligence, 
                i.guaranteed_speed, i.guaranteed_luck, i.guaranteed_charisma
         FROM player_inventory pi
         JOIN items i ON pi.item_id = i.id
         WHERE pi.player_id = :player_id AND pi.inventory_id = :inventory_id"
    );
    $stmt->bindValue(':player_id', $player_id, PDO::PARAM_INT);
    $stmt->bindValue(':inventory_id', $inventory_id, PDO::PARAM_INT);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
// --- アイテム情報を整理 ---
        $details['name'] = $item['name'];
        $details['options'] = []; // 配列として初期化

        // 1. 基本性能 (確定オプションとして扱う)
        if ($item['base_atk'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "基本ATK: +" . $item['base_atk']];
        if ($item['base_def'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "基本DEF: +" . $item['base_def']];
        
        // 2. 確定オプション
        if ($item['guaranteed_hp_max'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "最大HP: +" . $item['guaranteed_hp_max']];
        if ($item['guaranteed_strength'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "力: +" . $item['guaranteed_strength']];
        if ($item['guaranteed_vitality'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "体力: +" . $item['guaranteed_vitality']];
        if ($item['guaranteed_intelligence'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "賢さ: +" . $item['guaranteed_intelligence']];
        if ($item['guaranteed_speed'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "素早さ: +" . $item['guaranteed_speed']];
        if ($item['guaranteed_luck'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "運: +" . $item['guaranteed_luck']];
        if ($item['guaranteed_charisma'] > 0) $details['options'][] = ['type' => 'guaranteed', 'text' => "かっこよさ: +" . $item['guaranteed_charisma']];

        // 3. 不定オプション
        for ($i = 1; $i <= 5; $i++) {
            $stat_name = $item['option_' . $i . '_stat'];
            $stat_value = $item['option_' . $i . '_value'];
            if ($stat_name) {
                // $stat_name を日本語に変換（任意）
                $jp_stat_name = $stat_name; // TODO: 必要ならここで日本語に変換
                $details['options'][] = ['type' => 'random', 'text' => "ランダム: " . $jp_stat_name . " +" . $stat_value];
            }
        }
        
    } else {
        $details['error'] = "アイテムが見つかりません。";
    }

} catch (PDOException $e) {
    $details['error'] = "データベースエラー。";
}

// 結果をJSON形式でJavaScriptに返す
header('Content-Type: application/json');
echo json_encode($details);
exit();