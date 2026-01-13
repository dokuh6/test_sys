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
 * @param bool $is_html HTMLメールかどうか
 * @return bool
 */
function send_email_smtp($to, $subject, $body, $dbh = null, $is_html = false) {
    $mail = new PHPMailer(true);
    $status = 'failure';
    $error_message = '';

    $log_headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">" . "\r\n";
    $content_type = $is_html ? "text/html" : "text/plain";
    $log_headers .= "Content-Type: {$content_type}; charset=UTF-8";

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
        $mail->isHTML($is_html);
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

        $booking_number = isset($booking['booking_number']) ? $booking['booking_number'] : $booking['id'];
        $formatted_price = number_format($booking['total_price']);

        // HTML本文の構築
        $body = "
        <html>
        <head>
            <style>
                body { font-family: sans-serif; color: #333; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
                .header { background-color: #0f4c81; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 20px; background-color: #ffffff; }
                .booking-details { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .booking-details th, .booking-details td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
                .booking-details th { background-color: #f2f2f2; width: 40%; }
                .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                .button { display: inline-block; padding: 10px 20px; background-color: #0f4c81; color: #ffffff; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>ゲストハウス丸正</h1>
                    <p>ご予約ありがとうございます</p>
                </div>
                <div class='content'>
                    <p>{$booking['guest_name']} 様</p>
                    <p>この度は、ゲストハウス丸正にご予約いただき、誠にありがとうございます。<br>
                    以下の内容でご予約が確定いたしました。</p>

                    <table class='booking-details'>
                        <tr>
                            <th>予約番号</th>
                            <td><strong>{$booking_number}</strong></td>
                        </tr>
                        <tr>
                            <th>お部屋</th>
                            <td>{$booking['room_name']} ({$booking['type_name']})</td>
                        </tr>
                        <tr>
                            <th>チェックイン</th>
                            <td>{$booking['check_in_date']}</td>
                        </tr>
                        <tr>
                            <th>チェックアウト</th>
                            <td>{$booking['check_out_date']}</td>
                        </tr>
                        <tr>
                            <th>ご利用人数</th>
                            <td>{$booking['num_guests']}名様</td>
                        </tr>
                        <tr>
                            <th>合計金額</th>
                            <td><span style='color: #c0392b; font-weight: bold;'>¥{$formatted_price}</span></td>
                        </tr>
                    </table>

                    <p>当日はお気をつけてお越しください。<br>
                    スタッフ一同、お会いできることを心よりお待ちしております。</p>

                    <div style='text-align: center;'>
                        <a href='https://maps.google.com/?q=ゲストハウス丸正' class='button' style='color: #ffffff;'>地図を見る</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>ゲストハウス丸正<br>
                    〒000-0000 〇〇県〇〇市〇〇町1-2-3<br>
                    TEL: 00-0000-0000</p>
                    <p>※このメールは自動送信されています。</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // SMTP送信関数を呼び出し (HTML有効)
        return send_email_smtp($to, $subject, $body, $dbh, true);

    } catch (Exception $e) {
        error_log('Email sending failed in send_booking_confirmation_email: ' . $e->getMessage());
        return false;
    }
}

/**
 * 予約キャンセルメールを送信する
 * @param int $booking_id 予約ID
 * @param PDO $dbh データベース接続オブジェクト
 * @return bool 送信成功でtrue、失敗でfalse
 */
function send_cancellation_email($booking_id, $dbh) {
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
            return false;
        }

        // メール設定
        $to = $booking['guest_email'];
        $subject = '【ゲストハウス丸正】ご予約キャンセルのお知らせ';

        $booking_number = isset($booking['booking_number']) ? $booking['booking_number'] : $booking['id'];

        // HTML本文の構築
        $body = "
        <html>
        <head>
            <style>
                body { font-family: sans-serif; color: #333; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
                .header { background-color: #7f8c8d; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 20px; background-color: #ffffff; }
                .booking-details { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .booking-details th, .booking-details td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
                .booking-details th { background-color: #f2f2f2; width: 40%; }
                .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>ゲストハウス丸正</h1>
                    <p>ご予約キャンセル</p>
                </div>
                <div class='content'>
                    <p>{$booking['guest_name']} 様</p>
                    <p>以下のご予約のキャンセルを承りました。</p>

                    <table class='booking-details'>
                        <tr>
                            <th>予約番号</th>
                            <td><strong>{$booking_number}</strong></td>
                        </tr>
                        <tr>
                            <th>お部屋</th>
                            <td>{$booking['room_name']}</td>
                        </tr>
                        <tr>
                            <th>チェックイン</th>
                            <td>{$booking['check_in_date']}</td>
                        </tr>
                        <tr>
                            <th>チェックアウト</th>
                            <td>{$booking['check_out_date']}</td>
                        </tr>
                    </table>

                    <p>またのご利用を心よりお待ちしております。</p>
                </div>
                <div class='footer'>
                    <p>ゲストハウス丸正<br>
                    TEL: 00-0000-0000</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // 管理者にも通知を送る
        if (defined('ADMIN_EMAIL')) {
             // 簡易的に管理者へも同じ内容（あるいは通知専用）を送る
             send_email_smtp(ADMIN_EMAIL, "【管理者通知】予約キャンセル: {$booking_number}", "予約 {$booking_number} がキャンセルされました。", $dbh, false);
        }

        // ゲストへの送信
        return send_email_smtp($to, $subject, $body, $dbh, true);

    } catch (Exception $e) {
        error_log('Email sending failed in send_cancellation_email: ' . $e->getMessage());
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
