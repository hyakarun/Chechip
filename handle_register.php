<?php
// データベース接続ファイルを読み込む
require_once(__DIR__ . '/db_connect.php');

// フォームから送信されたデータを受け取る
$name = $_POST['name'];
$password = $_POST['password'];

// 簡単なバリデーション
if (empty($name) || empty($password)) {
    exit('名前とパスワードを入力してください。');
}

// パスワードを安全な形式にハッシュ化する
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // データベースに接続
    $pdo = connectDb();

    // playersテーブルに新しいプレイヤーを登録するSQL文を準備
    // :name などは後から値を入れるための「プレースホルダー」です
    $stmt = $pdo->prepare("INSERT INTO players (name, password_hash) VALUES (:name, :password_hash)");

    // プレースホルダーに実際の値を割り当てる
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':password_hash', $password_hash, PDO::PARAM_STR);

    // SQLを実行
    $stmt->execute();

    echo "キャラクター登録が完了しました！<br>";
    echo "ようこそ、" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . " さん！";
    // TODO: ここからゲーム画面にリダイレクトする処理を追加する

} catch (PDOException $e) {
    // もし名前が重複していた場合のエラー処理
    if ($e->getCode() == '23000') {
        exit('エラー: その名前は既に使用されています。別の名前を入力してください。');
    }
    // その他のデータベースエラー
    exit('データベースエラー: ' . $e->getMessage());
}
?>