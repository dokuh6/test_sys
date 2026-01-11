<?php
$root_path = '../';
require_once $root_path . 'includes/header.php';

$booking_number = $_GET['booking_number'] ?? '';

// 予約番号がない場合はトップへ
if (empty($booking_number)) {
    header('Location: ../index.php');
    exit();
}

// セキュリティチェック：セッションに保存された直近の予約番号と一致するか確認
// または、ログインユーザー本人の予約か確認
$is_valid_access = false;

if (isset($_SESSION['last_booking_number']) && $_SESSION['last_booking_number'] === $booking_number) {
    $is_valid_access = true;
    // 一度表示したらセッションから削除する（リロード対策やセキュリティ強化のため）
    // ただし、リロードで見られなくなるのも不便なので、削除しないか、別途フラグ管理するか。
    // ここでは削除しないでおく（ユーザー体験優先）
} elseif (isset($_SESSION['user'])) {
    // ログインユーザーの場合、自分の予約ならOK
    try {
        $check_sql = "SELECT count(*) FROM bookings WHERE booking_number = :bn AND user_id = :uid";
        $check_stmt = $dbh->prepare($check_sql);
        $check_stmt->execute([':bn' => $booking_number, ':uid' => $_SESSION['user']['id']]);
        if ($check_stmt->fetchColumn() > 0) {
            $is_valid_access = true;
        }
    } catch (PDOException $e) {
        // エラー無視
    }
}

if (!$is_valid_access) {
    // 不正なアクセスとしてエラー表示またはトップへ
    // 簡略化のためエラーメッセージを表示
    echo '<div style="max-width: 600px; margin: 20px auto; padding: 20px; color: red;">' . h(t('error_invalid_access')) . '</div>';
    require_once $root_path . 'includes/footer.php';
    exit();
}

// 予約情報を取得して表示
try {
    $sql = "SELECT b.*, r.name as room_name, rt.name as type_name
            FROM bookings b
            JOIN booking_rooms br ON b.id = br.booking_id
            JOIN rooms r ON br.room_id = r.id
            JOIN room_types rt ON r.room_type_id = rt.id
            WHERE b.booking_number = :bn";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([':bn' => $booking_number]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $error = t('error_booking_not_found');
    }
} catch (PDOException $e) {
    $error = t('error_db');
}
?>

<div style="max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; text-align: center;">
    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo h($error); ?></p>
        <p><a href="../index.php" class="btn"><?php echo h(t('btn_home')); ?></a></p>
    <?php else: ?>
        <h2 style="color: green;"><?php echo h(t('booking_complete_title')); ?></h2>
        <p><?php echo h(t('booking_complete_message')); ?></p>
        <p><?php echo h(t('booking_number')); ?>: <strong><?php echo h($booking['booking_number']); ?></strong></p>
        <p><?php echo h(t('room')); ?>: <?php echo h($booking['room_name']); ?> (<?php echo h($booking['type_name']); ?>)</p>
        <p><?php echo h(t('total_price')); ?>: ¥<?php echo number_format($booking['total_price']); ?></p>
        <p><a href="../index.php" class="btn"><?php echo h(t('btn_home')); ?></a></p>
    <?php endif; ?>
</div>

<?php
require_once $root_path . 'includes/footer.php';
?>
