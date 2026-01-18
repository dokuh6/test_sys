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
        // DB接続がない場合はログに記録して終了
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
 * 管理者/ユーザーの操作ログを記録する
 * @param PDO $dbh
 * @param int|null $user_id
 * @param string $action
 * @param array|string $details
 * @return bool
 */
function log_admin_action($dbh, $user_id, $action, $details = []) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_array($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE);
        }

        $sql = "INSERT INTO admin_logs (user_id, action, details, ip_address, created_at) VALUES (:user_id, :action, :details, :ip_address, NOW())";
        $stmt = $dbh->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => $ip_address
        ]);
        return true;
    } catch (Exception $e) {
        // エラーログに残すが処理は止めない
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
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
                    b.id, b.guest_name, b.guest_email, b.check_in_date, b.check_out_date, b.check_in_time, b.check_out_time, b.num_guests, b.num_children, b.notes, b.total_price,
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
        $subject = t('email_subject_confirmed');

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
                    <h1>" . t('site_title') . "</h1>
                    <p>" . t('email_header_confirmed') . "</p>
                </div>
                <div class='content'>
                    <p>" . t('email_greeting', h($booking['guest_name'])) . "</p>
                    <p>" . t('email_body_confirmed_1') . "<br>
                    " . t('email_body_confirmed_2') . "</p>

                    <table class='booking-details'>
                        <tr>
                            <th>" . t('booking_number') . "</th>
                            <td><strong>{$booking_number}</strong></td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_room') . "</th>
                            <td>" . h($booking['room_name']) . " (" . h($booking['type_name']) . ")</td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_check_in') . "</th>
                            <td>{$booking['check_in_date']} " . ($booking['check_in_time'] ? "({$booking['check_in_time']})" : "") . "</td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_check_out') . "</th>
                            <td>{$booking['check_out_date']} " . ($booking['check_out_time'] ? "({$booking['check_out_time']})" : "") . "</td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_guests') . "</th>
                            <td>{$booking['num_guests']}名 (子供: {$booking['num_children']}名)</td>
                        </tr>
                        " . ($booking['notes'] ? "<tr><th>" . t('book_notes_label') . "</th><td>" . nl2br(h($booking['notes'])) . "</td></tr>" : "") . "
                        <tr>
                            <th>" . t('booking_info_total_price') . "</th>
                            <td><span style='color: #c0392b; font-weight: bold;'>¥{$formatted_price}</span></td>
                        </tr>
                    </table>

                    <p>" . t('email_wait_message') . "<br>
                    " . t('email_wait_message_2') . "</p>

                    <div style='text-align: center;'>
                        <a href='https://maps.google.com/?q=" . t('site_title') . "' class='button' style='color: #ffffff;'>" . t('email_btn_map') . "</a>
                    </div>

                    <div style='border-top: 1px solid #eee; margin-top: 30px; padding-top: 20px;'>
                         <h3 style='font-size: 16px; margin-bottom: 10px; color: #555;'>" . t('info_guide_title') . "</h3>

                         <h4 style='font-size: 14px; margin: 15px 0 5px; color: #555;'>【" . t('info_pricing_title') . "】</h4>
                         <ul style='font-size: 13px; padding-left: 20px; color: #555;'>
                            <li>" . t('info_pricing_adult') . "</li>
                            <li>" . t('info_pricing_child') . "</li>
                            <li>" . t('info_pricing_infant') . "</li>
                            <li>" . t('info_pricing_payment') . "</li>
                         </ul>

                         <h4 style='font-size: 14px; margin: 15px 0 5px; color: #555;'>【" . t('info_checkin_title') . "】</h4>
                         <ul style='font-size: 13px; padding-left: 20px; color: #555;'>
                            <li>" . t('info_checkin_in') . "</li>
                            <li>" . t('info_checkin_out') . "</li>
                            <li>" . t('info_checkin_note') . "</li>
                         </ul>

                         <h4 style='font-size: 14px; margin: 15px 0 5px; color: #555;'>【" . t('info_booking_title') . "】</h4>
                         <ul style='font-size: 13px; padding-left: 20px; color: #555;'>
                            <li>" . t('info_booking_1') . "</li>
                            <li>" . t('info_booking_2') . "</li>
                            <li>" . t('info_booking_3') . "</li>
                            <li>" . t('info_booking_4') . "</li>
                         </ul>

                         <h4 style='font-size: 14px; margin: 15px 0 5px; color: #555;'>【" . t('info_cancel_title') . "】</h4>
                         <p style='font-size: 13px; margin: 5px 0; color: #555;'>" . t('info_cancel_desc') . "</p>
                         <ul style='font-size: 13px; padding-left: 20px; color: #555;'>
                            <li>" . t('info_cancel_policy_1') . "</li>
                            <li>" . t('info_cancel_policy_2') . "</li>
                            <li>" . t('info_cancel_policy_3') . "</li>
                            <li>" . t('info_cancel_policy_note') . "</li>
                            <li>" . t('info_cancel_policy_except') . "</li>
                         </ul>
                    </div>
                </div>
                <div class='footer'>
                    <p>" . t('site_title') . "<br>
                    " . t('email_footer_address_1') . "<br>
                    " . t('email_footer_tel') . "</p>
                    <p>" . t('email_footer_auto') . "</p>
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
        $subject = t('email_subject_cancelled');

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
                    <h1>" . t('site_title') . "</h1>
                    <p>" . t('email_header_cancelled') . "</p>
                </div>
                <div class='content'>
                    <p>" . t('email_greeting', h($booking['guest_name'])) . "</p>
                    <p>" . t('email_body_cancelled') . "</p>

                    <table class='booking-details'>
                        <tr>
                            <th>" . t('booking_number') . "</th>
                            <td><strong>{$booking_number}</strong></td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_room') . "</th>
                            <td>" . h($booking['room_name']) . "</td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_check_in') . "</th>
                            <td>{$booking['check_in_date']}</td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_check_out') . "</th>
                            <td>{$booking['check_out_date']}</td>
                        </tr>
                    </table>

                    <p>" . t('email_farewell') . "</p>
                </div>
                <div class='footer'>
                    <p>" . t('site_title') . "<br>
                    " . t('email_footer_tel') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // 管理者にも通知を送る
        if (defined('ADMIN_EMAIL')) {
             // 簡易的に管理者へも同じ内容（あるいは通知専用）を送る
             send_email_smtp(ADMIN_EMAIL, t('email_admin_subject_cancel', $booking_number), "予約 {$booking_number} がキャンセルされました。", $dbh, false);
        }

        // ゲストへの送信
        return send_email_smtp($to, $subject, $body, $dbh, true);

    } catch (Exception $e) {
        error_log('Email sending failed in send_cancellation_email: ' . $e->getMessage());
        return false;
    }
}

/**
 * 予約変更メールを送信する
 * @param int $booking_id 予約ID
 * @param PDO $dbh データベース接続オブジェクト
 * @return bool 送信成功でtrue、失敗でfalse
 */
function send_booking_modification_email($booking_id, $dbh) {
    try {
        // 予約情報を取得
        $sql = "SELECT
                    b.id, b.guest_name, b.guest_email, b.guest_phone, b.check_in_date, b.check_out_date, b.check_in_time, b.check_out_time, b.num_guests, b.num_children, b.notes, b.total_price,
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

        $booking_number = isset($booking['booking_number']) ? $booking['booking_number'] : $booking['id'];
        $formatted_price = number_format($booking['total_price']);

        // --- ゲスト向けメール ---
        $to_guest = $booking['guest_email'];
        $subject_guest = t('email_subject_modified');

        $body_guest = "
        <html>
        <head>
            <style>
                body { font-family: sans-serif; color: #333; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
                .header { background-color: #e67e22; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
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
                    <h1>" . t('site_title') . "</h1>
                    <p>" . t('email_header_modified') . "</p>
                </div>
                <div class='content'>
                    <p>" . t('email_greeting', h($booking['guest_name'])) . "</p>
                    <p>" . t('email_body_modified') . "<br>
                    " . t('email_body_modified_2') . "</p>

                    <table class='booking-details'>
                        <tr>
                            <th>" . t('booking_number') . "</th>
                            <td><strong>{$booking_number}</strong></td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_room') . "</th>
                            <td>" . h($booking['room_name']) . " (" . h($booking['type_name']) . ")</td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_check_in') . "</th>
                            <td>{$booking['check_in_date']} " . ($booking['check_in_time'] ? "({$booking['check_in_time']})" : "") . "</td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_check_out') . "</th>
                            <td>{$booking['check_out_date']} " . ($booking['check_out_time'] ? "({$booking['check_out_time']})" : "") . "</td>
                        </tr>
                        <tr>
                            <th>" . t('booking_info_guests') . "</th>
                            <td>{$booking['num_guests']}名 (子供: {$booking['num_children']}名)</td>
                        </tr>
                        " . ($booking['notes'] ? "<tr><th>" . t('book_notes_label') . "</th><td>" . nl2br(h($booking['notes'])) . "</td></tr>" : "") . "
                        <tr>
                            <th>" . t('booking_info_total_price') . "</th>
                            <td><span style='color: #c0392b; font-weight: bold;'>¥{$formatted_price}</span></td>
                        </tr>
                    </table>

                    <p>" . t('email_inquiry') . "</p>
                </div>
                <div class='footer'>
                    <p>" . t('site_title') . "<br>
                    " . t('email_footer_tel') . "</p>
                    <p>" . t('email_footer_auto') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // ゲストへ送信
        $guest_result = send_email_smtp($to_guest, $subject_guest, $body_guest, $dbh, true);

        // --- 管理者向けメール ---
        // 定数が未定義の場合は仮のアドレスまたはスキップ
        $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com';

        $subject_admin = t('email_admin_subject_modify', $booking_number, $booking['guest_name']);
        $body_admin = t('email_admin_body_modify') . "

        " . t('booking_number') . ": {$booking_number}
        " . t('form_name') . ": {$booking['guest_name']}
        " . t('form_tel') . ": {$booking['guest_phone']}
        " . t('booking_info_check_in') . ": {$booking['check_in_date']} ～ {$booking['check_out_date']}
        " . t('booking_info_guests') . ": {$booking['num_guests']}, " . t('pricing_child') . " {$booking['num_children']}
        " . t('booking_info_total_price') . ": ¥{$formatted_price}
        " . t('book_notes_label') . " {$booking['notes']}

        " . t('email_admin_check_panel') . "
        ";

        send_email_smtp($admin_email, $subject_admin, $body_admin, $dbh, false);

        return $guest_result;

    } catch (Exception $e) {
        error_log('Email sending failed in send_booking_modification_email: ' . $e->getMessage());
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

/**
 * ファイルアップロードのエラーコードを日本語メッセージに変換する
 * @param int $code $_FILES['...']['error'] の値
 * @return string 日本語エラーメッセージ
 */
function get_file_upload_error_message($code) {
    switch ($code) {
        case UPLOAD_ERR_OK:
            return 'エラーはありません。';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'ファイルサイズが大きすぎます (サーバー設定の上限を超えています)。';
        case UPLOAD_ERR_PARTIAL:
            return 'ファイルの一部しかアップロードされていません。';
        case UPLOAD_ERR_NO_FILE:
            return 'ファイルがアップロードされていません。';
        case UPLOAD_ERR_NO_TMP_DIR:
            return '一時保存フォルダが見つかりません。';
        case UPLOAD_ERR_CANT_WRITE:
            return 'ディスクへの書き込みに失敗しました。';
        case UPLOAD_ERR_EXTENSION:
            return 'PHPの拡張機能によってアップロードが停止されました。';
        default:
            return '不明なエラーが発生しました (コード: ' . $code . ')。';
    }
}
