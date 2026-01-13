<?php
require_once __DIR__ . '/includes/init.php';

// セッション変数をすべて解除する
$_SESSION = [];

// セッションクッキーを削除する
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破壊する
session_destroy();

// ログアウトメッセージを次のページに渡すためにセッションを再開する（任意）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['success_message'] = "ログアウトしました。";
header('Location: index.php');
exit();
