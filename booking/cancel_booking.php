<?php
$root_path = '../';
require_once $root_path . 'includes/header.php';

$booking_number = $_GET['booking_number'] ?? '';
$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    // キャンセル処理
    $booking_id = $_POST['booking_id'];

    try {
        // ステータス更新
        $sql = "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = :id";
        $stmt = $dbh->prepare($sql);
        $stmt->execute([':id' => $booking_id]);

        $success = t('cancel_success');

        // 予約情報を再取得してメール送信などに使う
        $stmt = $dbh->prepare("SELECT * FROM bookings WHERE id = :id");
        $stmt->execute([':id' => $booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        // キャンセルメール送信（簡略化）
        $subject = "【ゲストハウス丸正】予約キャンセルのお知らせ";
        $body = $booking['guest_name'] . "様\n\n予約（予約番号: " . $booking['booking_number'] . "）のキャンセルを承りました。\nまたのご利用をお待ちしております。";
        send_email_smtp($booking['guest_email'], $subject, $body, $dbh);

    } catch (PDOException $e) {
        $error = t('error_db');
    }

} else {
    // 予約情報の取得と表示
    if ($token) {
        // トークンによる検索（今回は未実装のためエラー）
        $error = "Invalid token.";
    } elseif ($booking_number && $email) {
        try {
            $sql = "SELECT b.*, r.name as room_name, rt.name as type_name
                    FROM bookings b
                    JOIN booking_rooms br ON b.id = br.booking_id
                    JOIN rooms r ON br.room_id = r.id
                    JOIN room_types rt ON r.room_type_id = rt.id
                    WHERE b.booking_number = :bn AND b.guest_email = :email";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([':bn' => $booking_number, ':email' => $email]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                $error = t('error_booking_not_found');
            }
        } catch (PDOException $e) {
            $error = t('error_db');
        }
    } else {
        $error = t('error_invalid_access');
    }
}

$csrf_token = generate_csrf_token();
?>

<div style="max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
    <h2><?php echo h(t('cancel_booking_title') ?? '予約キャンセル'); ?></h2>

    <?php if ($success): ?>
        <p style="color: green;"><?php echo h($success); ?></p>
        <p><a href="<?php echo $root_path; ?>index.php"><?php echo h(t('btn_home')); ?></a></p>
    <?php elseif ($error): ?>
        <p style="color: red;"><?php echo h($error); ?></p>
        <p><a href="check_booking.php"><?php echo h(t('btn_back')); ?></a></p>
    <?php elseif (isset($booking)): ?>
        <div style="margin-bottom: 20px;">
            <p><strong><?php echo h(t('booking_number')); ?>:</strong> <?php echo h($booking['booking_number']); ?></p>
            <p><strong><?php echo h(t('room')); ?>:</strong> <?php echo h($booking['room_name']); ?> (<?php echo h($booking['type_name']); ?>)</p>
            <p><strong><?php echo h(t('check_in')); ?>:</strong> <?php echo h($booking['check_in_date']); ?></p>
            <p><strong><?php echo h(t('check_out')); ?>:</strong> <?php echo h($booking['check_out_date']); ?></p>
            <p><strong><?php echo h(t('total_price')); ?>:</strong> ¥<?php echo number_format($booking['total_price']); ?></p>
            <p><strong><?php echo h(t('status')); ?>:</strong> <?php echo h($booking['status']); ?></p>
        </div>

        <?php if ($booking['status'] !== 'cancelled'): ?>
            <p><?php echo h(t('cancel_confirm_message') ?? 'この予約をキャンセルしますか？この操作は取り消せません。'); ?></p>
            <form action="" method="POST" onsubmit="return confirm('<?php echo h(t('cancel_confirm_alert') ?? '本当にキャンセルしますか？'); ?>');">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="booking_id" value="<?php echo h($booking['id']); ?>">
                <button type="submit" class="btn" style="background-color: #d9534f; color: white;"><?php echo h(t('btn_cancel_booking') ?? '予約をキャンセルする'); ?></button>
            </form>
        <?php else: ?>
            <p style="color: red;"><?php echo h(t('already_cancelled') ?? 'この予約は既にキャンセルされています。'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once $root_path . 'includes/footer.php';
?>
