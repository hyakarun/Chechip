<?php

// --------------------------------------------------
// データベース接続情報
// --------------------------------------------------
// ★★★ 以下の4つの項目を、あなたがメモしたものに書き換えてください ★★★
define('DB_HOST', 'mysql327.phy.lolipop.lan'); // ① データベースホスト
define('DB_NAME', 'LAA1529361-cheychip');   // ② データベース名
define('DB_USER', 'LAA1529361');               // ③ ユーザー名
define('DB_PASS', 'nohomeruLOMLOM12');          // ④ パスワード

// --------------------------------------------------
// データベースに接続するための関数
// --------------------------------------------------
function connectDb() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // 接続エラーが発生したら、メッセージを表示して終了する
        header('Content-Type: text/plain; charset=UTF-8', true, 500);
        exit('データベース接続エラー: ' . $e->getMessage());
    }
}