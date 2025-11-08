<?php
// データベース接続ファイルを読み込む
require_once(__DIR__ . '/db_connect.php'); //

// フォームから送信されたデータを受け取る
$name = $_POST['name']; //
$password = $_POST['password']; //

// 簡単なバリデーション
if (empty($name) || empty($password)) { //
    exit('名前とパスワードを入力してください。');
}

// パスワードを安全な形式にハッシュ化する
$password_hash = password_hash($password, PASSWORD_DEFAULT); //

try {
    // データベースに接続
    $pdo = connectDb(); //

    // --- ▼▼▼ (ここから修正) ▼▼▼ ---

    // 1. (重要) status_growth_table から level: 1 のデータを取得
    $stmt_status = $pdo->prepare("SELECT * FROM status_growth_table WHERE level = 1");
    $stmt_status->execute();
    $initial_stats = $stmt_status->fetch();

    if (!$initial_stats) {
        exit('レベル1のマスタデータが見つかりません。');
    }

    // 2. playersテーブルに「初期ステータス」と「登録情報」を登録するSQL文を準備
    $stmt = $pdo->prepare(
        "INSERT INTO players 
            (name, password_hash, level, exp, gold, hp, hp_max, 
             strength, vitality, intelligence, speed, luck, charisma) 
         VALUES 
            (:name, :password_hash, 1, 0, 0, :hp, :hp_max, 
             :strength, :vitality, :intelligence, :speed, :luck, :charisma)"
    );

    // 3. プレースホルダーに実際の値を割り当てる
    $stmt->bindValue(':name', $name, PDO::PARAM_STR); //
    $stmt->bindValue(':password_hash', $password_hash, PDO::PARAM_STR); //
    
    // (注: $initial_stats のカラム名がマスタテーブルと一致している必要があります)
    $stmt->bindValue(':hp', $initial_stats['hp_max'], PDO::PARAM_INT); // HPは最大HPで開始
    $stmt->bindValue(':hp_max', $initial_stats['hp_max'], PDO::PARAM_INT);
    $stmt->bindValue(':strength', $initial_stats['strength'], PDO::PARAM_INT);
    $stmt->bindValue(':vitality', $initial_stats['vitality'], PDO::PARAM_INT);
    $stmt->bindValue(':intelligence', $initial_stats['intelligence'], PDO::PARAM_INT);
    $stmt->bindValue(':speed', $initial_stats['speed'], PDO::PARAM_INT);
    $stmt->bindValue(':luck', $initial_stats['luck'], PDO::PARAM_INT);
    $stmt->bindValue(':charisma', $initial_stats['charisma'], PDO::PARAM_INT);


    // 4. SQLを実行
    $stmt->execute(); //

    // --- ▲▲▲ (修正ここまで) ▲▲▲ ---

    // 登録が完了したら、ログインページにリダイレクトする
    header('Location: login.php');
    exit();

} catch (PDOException $e) {
    // もし名前が重複していた場合のエラー処理
    if ($e->getCode() == '23000') { //
        exit('エラー: その名前は既に使用されています。別の名前を入力してください。'); //
    }
    // その他のデータベースエラー
    exit('データベースエラー: ' . $e->getMessage()); //
}
?>