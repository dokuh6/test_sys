<?php

/**
 * HTMLエスケープ（XSS対策）
 * @param string $s
 * @return string
 */
function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * 予約確認メールを送信する
 * @param int $booking_id 予約ID
 * @param PDO $dbh データベース接続オブジェクト
 * @return bool 送信成功でtrue、失敗でfalse
 */
function send_booking_confirmation_email($booking_id, $dbh) {
    try {
        // 予約情報を取得
        $sql = "SELECT
                    b.id, b.guest_name, b.guest_email, b.check_in_date, b.check_out_date, b.num_guests, b.total_price,
                    r.name as room_name, rt.name as type_name
                FROM bookings b
                JOIN booking_rooms br ON b.id = br.booking_id
                JOIN rooms r ON br.room_id = r.id
                JOIN room_types rt ON r.room_type_id = rt.id
                WHERE b.id = :booking_id";

        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
        $stmt->execute();
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            return false; // 予約が見つからない
        }

        // メール設定
        $to = $booking['guest_email'];
        $subject = '【ゲストハウス丸正】ご予約確定のお知らせ';
        $from_name = 'ゲストハウス丸正';
        $from_email = 'noreply@example.com'; // 送信元メールアドレス(サーバーに合わせて要変更)

        $headers = "From: " . mb_encode_mimeheader($from_name) . "<" . $from_email . ">\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        // メッセージ本文
        $body = "{$booking['guest_name']} 様\n\n";
        $body .= "この度は、ゲストハウス丸正にご予約いただき、誠にありがとうございます。\n";
        $body .= "以下の内容でご予約が確定いたしましたので、ご確認ください。\n\n";
        $body .= "-------------------------------------------------\n";
        $body .= "ご予約内容\n";
        $body .= "-------------------------------------------------\n";
        $body .= "予約番号: {$booking['id']}\n";
        $body .= "お部屋: {$booking['room_name']} ({$booking['type_name']})\n";
        $body .= "チェックイン日: {$booking['check_in_date']}\n";
        $body .= "チェックアウト日: {$booking['check_out_date']}\n";
        $body .= "ご利用人数: {$booking['num_guests']}名様\n";
        $body .= "合計金額: ¥" . number_format($booking['total_price']) . "\n\n";
        $body .= "-------------------------------------------------\n\n";
        $body .= "スタッフ一同、お会いできることを心よりお待ちしております。\n\n";
        $body .= "ゲストハウス丸正\n";
        $body .= "HP: (ここに実際のURLを記載)\n";

        // メール送信
        mb_language("Japanese");
        mb_internal_encoding("UTF-8");

        return mb_send_mail($to, $subject, $body, $headers);

    } catch (Exception $e) {
        // エラーログなどを記録すると良い
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * CSRFトークンを生成し、セッションに保存する
 * @return string 生成されたトークン
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * 送信されたCSRFトークンを検証する
 */
function validate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('不正なリクエストです。');
    }
    // トークンを一度使ったら削除
    unset($_SESSION['csrf_token']);
}
