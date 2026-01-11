<?php

// PHPMailerの読み込み
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\SMTP;

/**
 * HTMLエスケープ（XSS対策）
 * @param string $s
 * @return string
 */
function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * メール送信履歴を記録する
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param string $headers
 * @param string $status
 * @param string $error_message
 * @param PDO|null $dbh
 */
function log_email_history($to, $subject, $body, $headers, $status, $error_message = '', $dbh = null) {
    if (!$dbh) {
        global $dbh;
    }

    if (!$dbh) {
        // もし$dbhがない場合（稀なケース）、接続を試みるかログだけファイルに残すが、
        // ここではグローバル接続があると仮定。
        error_log("DB Connection not found in log_email_history");
        return;
    }

    try {
        $sql = "INSERT INTO email_logs (to_email, subject, body, headers, status, error_message, sent_at) VALUES (:to, :subject, :body, :headers, :status, :error_message, NOW())";
        $stmt = $dbh->prepare($sql);
        $stmt->execute([
            ':to' => $to,
            ':subject' => $subject,
            ':body' => $body,
            ':headers' => $headers,
            ':status' => $status,
            ':error_message' => $error_message
        ]);
    } catch (Exception $e) {
        error_log("Failed to log email history: " . $e->getMessage());
    }
}

/**
 * SMTPを使用してメールを送信する
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param PDO|null $dbh
 * @return bool
 */
function send_email_smtp($to, $subject, $body, $dbh = null) {
    $mail = new PHPMailer(true);
    $status = 'failure';
    $error_message = '';
    // ログ用にヘッダー情報を記録（PHPMailerが生成するものとは完全に一致しないが、主要な情報として）
    $log_headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">" . "\r\n";
    $log_headers .= "Content-Type: text/plain; charset=UTF-8";

    try {
        // サーバー設定
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // 送信元・送信先
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // コンテンツ
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        $status = 'success';
        $result = true;
    } catch (MailerException $e) {
        $error_message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        // PHPMailerの例外メッセージをログに残す
        $result = false;
    } catch (Exception $e) {
        $error_message = "An unexpected error occurred: " . $e->getMessage();
        $result = false;
    }

    log_email_history($to, $subject, $body, $log_headers, $status, $error_message, $dbh);

    return $result;
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
                    b.booking_number,
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

        // メッセージ本文
        $body = "{$booking['guest_name']} 様\n\n";
        $body .= "この度は、ゲストハウス丸正にご予約いただき、誠にありがとうございます。\n";
        $body .= "以下の内容でご予約が確定いたしましたので、ご確認ください。\n\n";
        $body .= "-------------------------------------------------\n";
        $body .= "ご予約内容\n";
        $body .= "-------------------------------------------------\n";
        $body .= "予約番号: " . (isset($booking['booking_number']) ? $booking['booking_number'] : $booking['id']) . "\n";
        $body .= "お部屋: {$booking['room_name']} ({$booking['type_name']})\n";
        $body .= "チェックイン日: {$booking['check_in_date']}\n";
        $body .= "チェックアウト日: {$booking['check_out_date']}\n";
        $body .= "ご利用人数: {$booking['num_guests']}名様\n";
        $body .= "合計金額: ¥" . number_format($booking['total_price']) . "\n\n";
        $body .= "-------------------------------------------------\n\n";
        $body .= "スタッフ一同、お会いできることを心よりお待ちしております。\n\n";
        $body .= "ゲストハウス丸正\n";
        $body .= "HP: (ここに実際のURLを記載)\n";

        // SMTP送信関数を呼び出し
        return send_email_smtp($to, $subject, $body, $dbh);

    } catch (Exception $e) {
        error_log('Email sending failed in send_booking_confirmation_email: ' . $e->getMessage());
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
