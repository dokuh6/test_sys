<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログインしているか、かつ管理者のロール(role=1)を持っているかを確認
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 1) {
    // 条件を満たさない場合は、エラーメッセージをセットしてログインページにリダイレクト
    $_SESSION['error_message'] = "このページにアクセスする権限がありません。管理者としてログインしてください。";
    // adminディレクトリからの相対パスで、一つ上の階層のlogin.phpを指定
    header('Location: ../login.php');
    exit();
}

// データベース接続と共通関数を読み込む
// このファイルは他のadminファイルから呼ばれるので、パスの基点は呼び出し元になる
// そのため、呼び出し元ファイルからの相対パスで指定する必要がある
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
