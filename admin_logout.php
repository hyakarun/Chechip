<?php
session_start();
$_SESSION = array();
session_destroy();
// 管理画面のログインページに戻す
header('Location: index.php');