<?php

require_once 'includes/mail_config.php';
require_once 'includes/phpmailer/src/Exception.php';
require_once 'includes/phpmailer/src/PHPMailer.php';
require_once 'includes/phpmailer/src/SMTP.php';

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
 * 翻訳ヘルパー関数
 * @param string $key
 * @param array $params
 * @return string
 */
function t($key, $params = []) {
    global $lang_data;
    $text = isset($lang_data[$key]) ? $lang_data[$key] : $key;
    if (!empty($params)) {
        foreach ($params as $k => $v) {
            $text = str_replace('%' . $k . '%', $v, $text);
        }
        // 数値インデックスの置換にも対応 (例: %0%, %1%)
        foreach ($params as $k => $v) {
             $text = str_replace('%' . $k . '%', $v, $text);
        }
        // sprintf形式の置換 (%d, %sなど)
        if (strpos($text, '%') !== false && count($params) > 0) {
             // 単純な置換で済ますか、vsprintfを使うか。
             // ここでは簡易的に、もしキーが数値ならvsprintfを試みる
             if (array_keys($params) === range(0, count($params) - 1)) {
                 $text = vsprintf($text, $params);
             }
        }
    }
    return $text;
}

/**
 * メール送信履歴を記録する
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param string $headers
 * @param string $status
 * @param string $error_message
 */
function log_email_history($to, $subject, $body, $headers, $status, $error_message = '') {
    // データベース接続を取得 (グローバル変数または引数で渡す必要があるが、ここでは新しく接続するかグローバルを利用)
    // functions.php は通常 db_connect.php の後に読み込まれるか、global $dbh が使える前提
    global $dbh;

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
 * @param string $to 送信先
 * @param string $subject 件名
 * @param string $body 本文
 * @param PDO $dbh DB接続
 * @return bool
 */
function send_email_smtp($to, $subject, $body, $dbh) {
    $mail = new PHPMailer(true);

    try {
        // SMTP設定
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        // 受信者設定
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // コンテンツ設定
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();

        // 成功ログ
        log_email_history($to, $subject, $body, '', 'success', '', $dbh);
        return true;

    } catch (Exception $e) {
        // エラーログ
        log_email_history($to, $subject, $body, '', 'failure', $mail->ErrorInfo, $dbh);
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

        return send_email_smtp($to, $subject, $body, $dbh);

    } catch (Exception $e) {
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
