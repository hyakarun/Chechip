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
        $details['options'] = []; // 表示用テキスト配列

        // --- ★追加: 比較計算用の数値データ配列 ---
        $stats = [
            'atk' => 0, 'def' => 0, 'hp_max' => 0,
            'strength' => 0, 'vitality' => 0, 'intelligence' => 0,
            'speed' => 0, 'luck' => 0, 'charisma' => 0
        ];

        // 1. 基本性能
        if ($item['base_atk'] > 0) {
            $details['options'][] = ['type' => 'guaranteed', 'text' => "基本ATK: +" . $item['base_atk']];
            $stats['atk'] += $item['base_atk'];
        }
        if ($item['base_def'] > 0) {
            $details['options'][] = ['type' => 'guaranteed', 'text' => "基本DEF: +" . $item['base_def']];
            $stats['def'] += $item['base_def'];
        }
        
        // 2. 確定オプション
        $guaranteed_map = [
            'guaranteed_hp_max' => ['stat' => 'hp_max', 'label' => '最大HP'],
            'guaranteed_strength' => ['stat' => 'strength', 'label' => '力'],
            'guaranteed_vitality' => ['stat' => 'vitality', 'label' => '体力'],
            'guaranteed_intelligence' => ['stat' => 'intelligence', 'label' => '賢さ'],
            'guaranteed_speed' => ['stat' => 'speed', 'label' => '素早さ'],
            'guaranteed_luck' => ['stat' => 'luck', 'label' => '運'],
            'guaranteed_charisma' => ['stat' => 'charisma', 'label' => 'かっこよさ'],
        ];

        foreach ($guaranteed_map as $db_col => $info) {
            if ($item[$db_col] > 0) {
                $details['options'][] = ['type' => 'guaranteed', 'text' => $info['label'] . ": +" . $item[$db_col]];
                $stats[$info['stat']] += $item[$db_col];
            }
        }

        // 3. 不定オプション (ランダム付与)
        // stat名の日本語変換マップ
        $stat_labels = [
            'hp_max' => '最大HP', 'strength' => '力', 'vitality' => '体力',
            'intelligence' => '賢さ', 'speed' => '素早さ', 'luck' => '運',
            'charisma' => 'かっこよさ', 'atk' => 'ATK', 'def' => 'DEF'
        ];

        for ($i = 1; $i <= 5; $i++) {
            $stat_name = $item['option_' . $i . '_stat'];
            $stat_value = $item['option_' . $i . '_value'];
            if ($stat_name && $stat_value) {
                $jp_stat_name = $stat_labels[$stat_name] ?? $stat_name;
                $details['options'][] = ['type' => 'random', 'text' => "ランダム: " . $jp_stat_name . " +" . $stat_value];
                
                if (isset($stats[$stat_name])) {
                    $stats[$stat_name] += $stat_value;
                }
            }
        }

        // 数値データをレスポンスに追加
        $details['stats'] = $stats;
        
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