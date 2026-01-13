<?php
require_once __DIR__ . '/../includes/init.php';

// ログインしているか、かつ管理者のロール(role=1)を持っているかを確認
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 1) {
    // 条件を満たさない場合は、エラーメッセージをセットしてログインページにリダイレクト
    $_SESSION['error_message'] = "このページにアクセスする権限がありません。管理者としてログインしてください。";
    // adminディレクトリからの相対パスで、一つ上の階層のlogin.phpを指定
    header('Location: ../login.php');
    exit();
}

// init.phpでDB接続と共通関数は読み込まれているので不要
// require_once __DIR__ . '/../includes/db_connect.php';
// require_once __DIR__ . '/../includes/functions.php';
?>
