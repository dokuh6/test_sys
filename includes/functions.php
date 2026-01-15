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
                            <td>{$booking['check_in_date']} " . ($booking['check_in_time'] ? "({$booking['check_in_time']})" : "") . "</td>
                        </tr>
                        <tr>
                            <th>チェックアウト</th>
                            <td>{$booking['check_out_date']} " . ($booking['check_out_time'] ? "({$booking['check_out_time']})" : "") . "</td>
                        </tr>
                        <tr>
                            <th>ご利用人数</th>
                            <td>大人: {$booking['num_guests']}名, 子供: {$booking['num_children']}名</td>
                        </tr>
                        " . ($booking['notes'] ? "<tr><th>備考</th><td>" . nl2br(h($booking['notes'])) . "</td></tr>" : "") . "
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

                    <div style='border-top: 1px solid #eee; margin-top: 30px; padding-top: 20px;'>
                         <h3 style='font-size: 16px; margin-bottom: 10px; color: #555;'>ご利用案内</h3>

                         <h4 style='font-size: 14px; margin: 15px 0 5px; color: #555;'>【宿泊料金について】</h4>
                         <ul style='font-size: 13px; padding-left: 20px; color: #555;'>
                            <li>大人料金: 4,500円（お一人）</li>
                            <li>子供料金（中学生まで）: 2,500円（寝具持込み）</li>
                            <li>※乳幼児（5歳まで）など布団無しで添い寝の場合は無料</li>
                            <li>※お支払いは当日現金でお支払いをお願い致します。</li>
                         </ul>

                         <h4 style='font-size: 14px; margin: 15px 0 5px; color: #555;'>【チェックイン・チェックアウトについて】</h4>
                         <ul style='font-size: 13px; padding-left: 20px; color: #555;'>
                            <li>チェックイン: 15時～（20時以降到着の方は、当日電話にてご連絡ください）</li>
                            <li>チェックアウト: 10時</li>
                            <li>※日中・夜間共、宿泊者がいない時は宿を留守にすることもあります。宿に来る前に必ず電話で連絡してください。</li>
                         </ul>

                         <h4 style='font-size: 14px; margin: 15px 0 5px; color: #555;'>【宿泊予約について】</h4>
                         <ul style='font-size: 13px; padding-left: 20px; color: #555;'>
                            <li>当サイトまたはお電話でご予約をお願いします。（電話問合せ: 8時～20時）</li>
                            <li>予約の際は宿泊日・人数（男女別）をお知らせください。</li>
                            <li>当日予約・飛込みも可能ですが、極力事前にご予約下さい。</li>
                            <li>団体・グループ等の貸切はお早めにご連絡ください。</li>
                         </ul>

                         <h4 style='font-size: 14px; margin: 15px 0 5px; color: #555;'>【キャンセルについて】</h4>
                         <p style='font-size: 13px; margin: 5px 0; color: #555;'>他のお客様のご迷惑となりますので、人数変更・キャンセルの場合は早めにご連絡ください。</p>
                         <ul style='font-size: 13px; padding-left: 20px; color: #555;'>
                            <li>5日前: 30％</li>
                            <li>2日前: 50％</li>
                            <li>前日、当日、無断キャンセル: 100％</li>
                            <li>※貸切予約および繁忙期（GW、お盆、年末年始）は、7日前より50％、5日前より70％、3日前より100％となります。</li>
                            <li>（天災及びアクシデントによる場合は除きます）</li>
                         </ul>
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
        $subject_guest = '【ゲストハウス丸正】ご予約内容変更のお知らせ';

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
                    <h1>ゲストハウス丸正</h1>
                    <p>予約内容変更のお知らせ</p>
                </div>
                <div class='content'>
                    <p>{$booking['guest_name']} 様</p>
                    <p>いつもご利用ありがとうございます。<br>
                    ご予約内容の変更を承りました。以下の内容をご確認ください。</p>

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
                            <td>{$booking['check_in_date']} " . ($booking['check_in_time'] ? "({$booking['check_in_time']})" : "") . "</td>
                        </tr>
                        <tr>
                            <th>チェックアウト</th>
                            <td>{$booking['check_out_date']} " . ($booking['check_out_time'] ? "({$booking['check_out_time']})" : "") . "</td>
                        </tr>
                        <tr>
                            <th>ご利用人数</th>
                            <td>大人: {$booking['num_guests']}名, 子供: {$booking['num_children']}名</td>
                        </tr>
                        " . ($booking['notes'] ? "<tr><th>備考</th><td>" . nl2br(h($booking['notes'])) . "</td></tr>" : "") . "
                        <tr>
                            <th>合計金額</th>
                            <td><span style='color: #c0392b; font-weight: bold;'>¥{$formatted_price}</span></td>
                        </tr>
                    </table>

                    <p>ご不明な点がございましたら、お気軽にお問い合わせください。</p>
                </div>
                <div class='footer'>
                    <p>ゲストハウス丸正<br>
                    TEL: 00-0000-0000</p>
                    <p>※このメールは自動送信されています。</p>
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

        $subject_admin = "【管理者通知】予約変更: {$booking_number} ({$booking['guest_name']}様)";
        $body_admin = "
        以下の予約が変更されました。

        予約番号: {$booking_number}
        氏名: {$booking['guest_name']}
        電話番号: {$booking['guest_phone']}
        日程: {$booking['check_in_date']} ～ {$booking['check_out_date']}
        人数: 大人 {$booking['num_guests']}名, 子供 {$booking['num_children']}名
        合計金額: ¥{$formatted_price}
        備考: {$booking['notes']}

        管理画面で詳細をご確認ください。
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
