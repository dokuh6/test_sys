<?php
// CLI実行のみ許可する場合
if (php_sapi_name() !== 'cli' && (!isset($_GET['secret_key']) || $_GET['secret_key'] !== 'YOUR_SECRET_KEY')) {
    // Webからのアクセスの場合、何らかの認証が必要
    http_response_code(403);
    die('Access Denied');
}

// データベース接続と共通関数
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// DB接続 (includes/init.php を使うとセッション開始してしまうので、手動接続)
try {
    $dsn = 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST . ';charset=utf8mb4';
    $dbh = new PDO($dsn, DB_USER, DB_PASS);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// 言語設定 (デフォルトは日本語)
$current_lang = 'ja';
$lang_file = __DIR__ . "/lang/{$current_lang}.json";
if (file_exists($lang_file)) {
    $translations = json_decode(file_get_contents($lang_file), true);
} else {
    $translations = [];
}

function t_local($key, ...$args) {
    global $translations;
    $text = isset($translations[$key]) ? $translations[$key] : $key;
    if (!empty($args)) {
        return vsprintf($text, $args);
    }
    return $text;
}

// t()関数を上書きできないため、cron内では独自に処理するか、
// includes/functions.php の t() がグローバル変数を参照しているならそれを利用する。
// functions.php 内の t() は global $translations を使っているので、上でセットすれば動くはず。

echo "Starting automated email process...\n";

// --- 1. リマインダーメール (チェックイン3日前) ---
// 条件: check_in_date = Today + 3 days AND reminder_sent = 0 AND status = 'confirmed'
$reminder_date = date('Y-m-d', strtotime('+3 days'));
echo "Checking reminders for check-in: $reminder_date\n";

$sql = "SELECT * FROM bookings WHERE check_in_date = :target_date AND reminder_sent = 0 AND status = 'confirmed'";
$stmt = $dbh->prepare($sql);
$stmt->execute([':target_date' => $reminder_date]);
$reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($reminders as $booking) {
    echo "Sending reminder to Booking ID: {$booking['id']} ({$booking['guest_email']})\n";

    // 言語切り替えロジックが必要ならここで $translations を再ロードするが、今回は日本語固定とする
    // もし顧客ごとに言語があるなら bookings テーブルに lang カラムが必要

    $subject = t_local('email_subject_reminder');
    $to = $booking['guest_email'];
    $guest_name = $booking['guest_name'];

    // HTML本文
    $body = "
    <html>
    <head>
        <style>
            body { font-family: sans-serif; color: #333; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
            .header { background-color: #27ae60; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background-color: #ffffff; }
            .button { display: inline-block; padding: 10px 20px; background-color: #27ae60; color: #ffffff; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . t_local('site_title') . "</h1>
                <p>" . t_local('email_header_reminder') . "</p>
            </div>
            <div class='content'>
                <p>" . t_local('email_greeting', h($guest_name)) . "</p>
                <p>" . t_local('email_body_reminder') . "</p>

                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr>
                        <th style='padding: 8px; text-align: left; border-bottom: 1px solid #eee;'>" . t_local('booking_info_check_in') . "</th>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$booking['check_in_date']} " . ($booking['check_in_time'] ? "({$booking['check_in_time']})" : "") . "</td>
                    </tr>
                    <tr>
                        <th style='padding: 8px; text-align: left; border-bottom: 1px solid #eee;'>" . t_local('booking_info_room') . "</th>
                        <td style='padding: 8px; border-bottom: 1px solid #eee;'>" . t_local('booking_number') . ": " . ($booking['booking_number'] ?? $booking['id']) . "</td>
                    </tr>
                </table>

                <div style='text-align: center;'>
                    <a href='https://maps.google.com/?q=" . t_local('site_title') . "' class='button'>" . t_local('email_btn_map') . "</a>
                </div>

                <div style='margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-radius: 4px;'>
                    <h3 style='margin-top: 0; font-size: 16px;'>" . t_local('info_checkin_title') . "</h3>
                    <p style='font-size: 14px;'>" . t_local('info_checkin_in') . "</p>
                    <p style='font-size: 14px;'>" . t_local('info_pricing_payment') . "</p>
                </div>
            </div>
            <div class='footer'>
                <p>" . t_local('site_title') . "<br>
                " . t_local('email_footer_tel') . "</p>
                <p>" . t_local('email_footer_auto') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";

    if (send_email_smtp($to, $subject, $body, $dbh, true)) {
        $update = $dbh->prepare("UPDATE bookings SET reminder_sent = 1 WHERE id = :id");
        $update->execute([':id' => $booking['id']]);
        echo "  -> Sent successfully.\n";
    } else {
        echo "  -> Failed to send.\n";
    }
}


// --- 2. サンキューメール (チェックアウト翌日) ---
// 条件: check_out_date = Yesterday AND thankyou_sent = 0 AND status = 'confirmed' AND checkin_status = 'checked_in' (or checked_out if tracked)
// 今回は checkin_status 関係なく、日付と支払い状況(paid?)で判断するか、単純に日付で判断。
// confirmed予約で日付が過ぎていれば宿泊したとみなすのが一般的。

$thankyou_date = date('Y-m-d', strtotime('-1 day'));
echo "Checking thank-you emails for check-out: $thankyou_date\n";

$sql = "SELECT * FROM bookings WHERE check_out_date = :target_date AND thankyou_sent = 0 AND status = 'confirmed'";
$stmt = $dbh->prepare($sql);
$stmt->execute([':target_date' => $thankyou_date]);
$thankyous = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($thankyous as $booking) {
    echo "Sending thank-you to Booking ID: {$booking['id']} ({$booking['guest_email']})\n";

    $subject = t_local('email_subject_thankyou');
    $to = $booking['guest_email'];
    $guest_name = $booking['guest_name'];

    $body = "
    <html>
    <head>
        <style>
            body { font-family: sans-serif; color: #333; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
            .header { background-color: #e74c3c; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background-color: #ffffff; }
            .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . t_local('site_title') . "</h1>
                <p>" . t_local('email_header_thankyou') . "</p>
            </div>
            <div class='content'>
                <p>" . t_local('email_greeting', h($guest_name)) . "</p>
                <p>" . t_local('email_body_thankyou') . "</p>
                <p>" . t_local('email_farewell') . "</p>
            </div>
            <div class='footer'>
                <p>" . t_local('site_title') . "</p>
                <p>" . t_local('email_footer_auto') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";

    if (send_email_smtp($to, $subject, $body, $dbh, true)) {
        $update = $dbh->prepare("UPDATE bookings SET thankyou_sent = 1 WHERE id = :id");
        $update->execute([':id' => $booking['id']]);
        echo "  -> Sent successfully.\n";
    } else {
        echo "  -> Failed to send.\n";
    }
}

echo "Process completed.\n";
