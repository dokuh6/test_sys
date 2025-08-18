<?php
require_once 'includes/language.php'; // For session start and db connection
require_once 'includes/functions.php'; // For csrf validation

// POSTリクエストでなければトップへ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// CSRFトークンを検証
validate_csrf_token();

// ログインしているか確認
if (!isset($_SESSION['user'])) {
    // ログインしていなければログインページへ
    $_SESSION['error_message'] = "予約をキャンセルするにはログインが必要です。";
    header('Location: login.php');
    exit();
}

$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user']['id'];

if (!$booking_id) {
    // booking_idが無効
    $_SESSION['error_message'] = "無効なリクエストです。";
    header('Location: mypage.php');
    exit();
}

try {
    // 予約が本当にこのユーザーのものか確認
    $sql_check = "SELECT user_id FROM bookings WHERE id = :booking_id";
    $stmt_check = $dbh->prepare($sql_check);
    $stmt_check->execute([':booking_id' => $booking_id]);
    $booking_owner = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($booking_owner && $booking_owner['user_id'] == $user_id) {
        // 所有者であることが確認できたので、ステータスを更新
        $sql_update = "UPDATE bookings SET status = 'cancelled' WHERE id = :booking_id";
        $stmt_update = $dbh->prepare($sql_update);
        if ($stmt_update->execute([':booking_id' => $booking_id])) {
            $_SESSION['message'] = "予約番号 " . h($booking_id) . " をキャンセルしました。";
        } else {
            $_SESSION['error_message'] = "予約のキャンセル処理に失敗しました。";
        }
    } else {
        // 予約が存在しない、または所有者でない
        $_SESSION['error_message'] = "この予約をキャンセルする権限がありません。";
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "データベースエラーが発生しました。";
    // error_log($e->getMessage()); // エラーをログに記録
}

header('Location: mypage.php');
exit();
