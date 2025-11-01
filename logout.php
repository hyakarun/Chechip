<?php
session_start();

// セッション変数をすべて解除
$_SESSION = array();

// セッションを破棄
session_destroy();

// ログイン画面へリダイレクト
header('Location: login.php');
exit();