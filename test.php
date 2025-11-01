<?php

// 先ほど作成した接続ファイルを読み込む
require_once(__DIR__ . '/db_connect.php');

// データベースに接続してみる
$pdo = connectDb();

// 接続に成功したかどうかを画面に表示する
echo 'データベースへの接続に成功しました！';

?>