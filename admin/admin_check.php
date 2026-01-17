<?php
require_once __DIR__ . '/../includes/init.php';

// ログイン確認
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = "ログインしてください。";
    header('Location: ../login.php');
    exit();
}

// ユーザー情報の再取得（権限変更などを反映させるため推奨だが、
// ここではセッションの値を信じるか、あるいはinit.phpでリフレッシュするかが設計次第。
// 簡易的にセッションのroleを見る）
$user_role = $_SESSION['user']['role'];

// 管理者(1), フロント(2), 清掃(3) のいずれかでなければアクセス拒否
// 一般ユーザー(0)は管理画面にアクセスできない
if ($user_role != ROLE_MANAGER && $user_role != ROLE_STAFF && $user_role != ROLE_CLEANER) {
    $_SESSION['error_message'] = "このページにアクセスする権限がありません。";
    header('Location: ../login.php');
    exit();
}

/**
 * 必要な権限をチェックし、不足している場合はエラー画面へ遷移または停止する
 * @param array|int $allowed_roles 許可されるロールの配列または単一のロール
 */
function require_permission($allowed_roles) {
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    $current_role = $_SESSION['user']['role'];

    if (!in_array($current_role, $allowed_roles)) {
        // 権限エラー
        $_SESSION['error_message'] = "この機能にアクセスする権限がありません。";
        // 遷移先はリファラがあればリファラ、なければダッシュボード
        $url = 'bookings.php'; // デフォルト
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false) {
             // 無限ループ防止のため自分自身でないことを確認（簡易的）
             if (basename($_SERVER['HTTP_REFERER']) !== basename($_SERVER['PHP_SELF'])) {
                 $url = $_SERVER['HTTP_REFERER'];
             }
        }
        header("Location: $url");
        exit();
    }
}
?>
