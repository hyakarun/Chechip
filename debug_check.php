<?php
session_start();
require_once(__DIR__ . '/db_connect.php');

echo "<h1>Cheychip 装備データ診断ツール</h1>";

// 1. ログイン状態の確認
if (!isset($_SESSION['player_id'])) {
    echo "<p style='color:red;'>エラー: ログインしていません。先にログインしてください。</p>";
    echo "<a href='login.php'>ログインページへ</a>";
    exit();
}

$player_id = $_SESSION['player_id'];
echo "<p>現在のプレイヤーID: <strong>" . htmlspecialchars($player_id) . "</strong></p>";

try {
    $pdo = connectDb();

    // 2. プレイヤー名の取得
    $stmt_p = $pdo->prepare("SELECT name FROM players WHERE player_id = :pid");
    $stmt_p->bindValue(':pid', $player_id, PDO::PARAM_INT);
    $stmt_p->execute();
    $p_name = $stmt_p->fetchColumn();
    echo "<p>プレイヤー名: <strong>" . htmlspecialchars($p_name) . "</strong></p>";

    echo "<hr>";

    // 3. 「装備中(is_equipped=1)」のデータがあるか確認 (player_inventoryテーブル単体)
    $stmt_raw = $pdo->prepare("SELECT * FROM player_inventory WHERE player_id = :pid AND is_equipped = 1");
    $stmt_raw->bindValue(':pid', $player_id, PDO::PARAM_INT);
    $stmt_raw->execute();
    $raw_rows = $stmt_raw->fetchAll();

    echo "<h3>① データベース上の「装備中フラグ」チェック</h3>";
    if (empty($raw_rows)) {
        echo "<p style='color:red;'><strong>警告: `player_inventory` テーブルに、装備中(is_equipped=1)のアイテムが1つもありません。</strong></p>";
        echo "<p>→ 原因: 装備処理がうまくいっていないか、何らかの理由で解除されています。もう一度「装備変更」画面で装備し直してみてください。</p>";
    } else {
        echo "<p style='color:green;'>正常: " . count($raw_rows) . " 個のアイテムが装備中として記録されています。</p>";
        echo "<ul>";
        foreach ($raw_rows as $row) {
            echo "<li>InventoryID: " . $row['inventory_id'] . " / ItemID: " . $row['item_id'] . "</li>";
        }
        echo "</ul>";
    }

    echo "<hr>";

    // 4. アイテム情報と紐付けた取得テスト (game.phpと同じロジック)
    echo "<h3>② アイテム詳細情報の結合チェック</h3>";
    $sql = "SELECT pi.inventory_id, i.name, i.equip_slot 
            FROM player_inventory pi
            JOIN items i ON pi.item_id = i.id
            WHERE pi.player_id = :pid AND pi.is_equipped = 1";
    
    $stmt_join = $pdo->prepare($sql);
    $stmt_join->bindValue(':pid', $player_id, PDO::PARAM_INT);
    $stmt_join->execute();
    $joined_rows = $stmt_join->fetchAll();

    if (empty($joined_rows)) {
        if (!empty($raw_rows)) {
            echo "<p style='color:red;'><strong>重大なエラー: 装備フラグは立っていますが、アイテム情報(itemsテーブル)と結合できませんでした。</strong></p>";
            echo "<p>→ 原因: 存在しないアイテムIDを持っている可能性があります。</p>";
        } else {
            echo "<p>表示するデータがありません。</p>";
        }
    } else {
        echo "<table border='1' cellpadding='5'><tr><th>装備箇所(equip_slot)</th><th>アイテム名(name)</th><th>判定</th></tr>";
        
        $slots_check = ['right_hand', 'left_hand'];
        $found_slots = [];

        foreach ($joined_rows as $row) {
            $slot = $row['equip_slot'];
            $found_slots[] = $slot;
            // 空白文字チェック
            $len_orig = strlen($slot);
            $len_trim = strlen(trim($slot));
            $note = ($len_orig !== $len_trim) ? "<span style='color:red'>空白が含まれています！</span>" : "OK";

            echo "<tr>";
            echo "<td>[" . htmlspecialchars($slot) . "]</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . $note . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // 右手・左手の確認
        echo "<p><strong>ステータス:</strong><br>";
        if (in_array('right_hand', $found_slots)) {
            echo "右手: <span style='color:green'>OK</span><br>";
        } else {
            echo "右手: <span style='color:red'>見つかりません</span><br>";
        }
        if (in_array('left_hand', $found_slots)) {
            echo "左手: <span style='color:green'>OK</span><br>";
        } else {
            echo "左手: <span style='color:blue'>未装備 (または見つかりません)</span><br>";
        }
        echo "</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>データベースエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<a href='game.php'>ホームに戻る</a>";
?>