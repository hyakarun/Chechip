<?php
// admin_handle_generate_exp.php
session_start();
// 管理者ログインとPOSTメソッドをチェック
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
require_once(__DIR__ . '/../db_connect.php'); //

try {
    // フォームからデータを受け取る
    $max_level = (int)$_POST['max_level'];
    $initial_exp = (int)$_POST['initial_exp'];
    $level_breakpoints = $_POST['level_breakpoint']; // 配列
    $exp_increases = $_POST['exp_increase'];       // 配列

    if ($max_level <= 1 || $initial_exp <= 0 || empty($level_breakpoints) || empty($exp_increases)) {
        throw new Exception('入力パラメータが不正です。');
    }

    // ブレークポイントを [level => increase] の形式に整理
    $breakpoints = [];
    for ($i = 0; $i < count($level_breakpoints); $i++) {
        $level_limit = (int)$level_breakpoints[$i];
        $increase_amount = (int)$exp_increases[$i];
        // (レベル上限が重複しないようにキーを設定)
        $breakpoints[$level_limit] = $increase_amount;
    }
    // レベルの昇順にソート (重要)
    ksort($breakpoints);

    $pdo = connectDb();
    
    // 1. 既存のテーブルを空にする
    $pdo->query("TRUNCATE TABLE experience_table");
    
    $sql = "INSERT INTO experience_table (level, required_exp) VALUES (:level, :required_exp)";
    $stmt = $pdo->prepare($sql);
    
    $pdo->beginTransaction();

    $current_exp_needed = 0; // 現在のレベルアップに必要な「区間経験値」
    $total_exp = 0;          // 「累計経験値」
    $current_increase = 0;   // 現在適用中の「増加量」

    // 2. レベル2から最大レベルまでループ
    for ($level = 2; $level <= $max_level; $level++) {
        
        if ($level == 2) {
            // Lv 1 -> 2
            $current_exp_needed = $initial_exp;
        } else {
            // 3. Lv 3以降: 現在のレベルがどの段階かチェック
            $increase_found = false;
            foreach ($breakpoints as $level_limit => $increase_amount) {
                if ($level <= $level_limit) {
                    $current_increase = $increase_amount;
                    $increase_found = true;
                    break;
                }
            }
            // もし設定された上限レベルを超えた場合 (例: Lv501以降)、最後の段階の増加量を使い続ける
            if (!$increase_found) {
                $current_increase = end($breakpoints); // 配列の最後の要素
            }
            
            // 区間経験値を加算
            $current_exp_needed += $current_increase;
        }
        
        // 4. 「累計経験値」を計算
        $total_exp += $current_exp_needed;
        
        // 5. データベースにINSERT
        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->bindValue(':required_exp', $total_exp, PDO::PARAM_STR); // (BIGINT対応のためSTR型で)
        $stmt->execute();
    }
    
    $pdo->commit();
    
    // 完了したらフォーム画面に戻る
    header('Location: admin_generate_exp.php?success=1');
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    $error_message = 'データベースエラー: ' . $e->getMessage();
    if ($e->getCode() == '22003') {
        $error_message = 'エラー: 累計経験値が大きすぎます。experience_table の required_exp カラムの型を BIGINT に変更してください。';
    }
    header('Location: admin_generate_exp.php?error=' . urlencode($error_message));
    exit();
} catch (Exception $e) {
    header('Location: admin_generate_exp.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>