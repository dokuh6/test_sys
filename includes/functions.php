<?php

// PHPMailerの読み込み (Composer未使用のため手動require)
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
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
 * 汎用メール送信関数 (SMTP版)
 *
 * @param string $to 送信先メールアドレス
 * @param string $subject 件名
 * @param string $body 本文
 * @param string|null $from_name 送信者名 (指定がない場合は設定ファイルのデフォルト)
 * @param string|null $from_email 送信元メールアドレス (指定がない場合は設定ファイルのデフォルト)
 * @return bool 送信成功:true, 失敗:false
 */
function send_email_smtp($to, $subject, $body, $from_name = null, $from_email = null) {
    $mail = new PHPMailer(true);

    try {
        // サーバー設定
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // 文字コード設定
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // 送信元・送信先
        $senderName = $from_name ?: MAIL_FROM_NAME;
        $senderEmail = $from_email ?: MAIL_FROM_ADDRESS;

        $mail->setFrom($senderEmail, $senderName);
        $mail->addAddress($to);

        // コンテンツ
        $mail->isHTML(false); // テキストメール
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
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

        // メール内容
        $to = $booking['guest_email'];
        $subject = '【ゲストハウス丸正】ご予約確定のお知らせ';

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

        // SMTP送信関数を利用
        return send_email_smtp($to, $subject, $body);

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
