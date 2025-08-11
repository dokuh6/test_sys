<?php
require_once 'includes/header.php';

// ログインチェック
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = "マイページを閲覧するにはログインが必要です。";
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

// ユーザーの予約履歴を取得
try {
    $sql = "SELECT
                b.id,
                b.check_in_date,
                b.check_out_date,
                b.total_price,
                b.status,
                r.name AS room_name,
                r.name_en AS room_name_en
            FROM bookings AS b
            JOIN booking_rooms AS br ON b.id = br.booking_id
            JOIN rooms AS r ON br.room_id = r.id
            WHERE b.user_id = :user_id
            ORDER BY b.check_in_date DESC";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("データベースエラー: " . h($e->getMessage()));
}


?>
<style>
.mypage-container {
    max-width: 800px;
    margin: 20px auto;
}
.booking-history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.booking-history-table th, .booking-history-table td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: left;
}
.booking-history-table th {
    background-color: #f2f2f2;
}
.status-confirmed { color: green; font-weight: bold; }
.status-cancelled { color: red; text-decoration: line-through; }
</style>

<div class="mypage-container">
    <h2><?php echo h(t('mypage_title')); ?></h2>
    <p><?php echo h(t('mypage_welcome', $_SESSION['user']['name'])); ?></p>

    <hr>

    <h3><?php echo h(t('mypage_history_title')); ?></h3>
    <?php if (empty($bookings)): ?>
        <p><?php echo h(t('mypage_no_bookings')); ?></p>
    <?php else: ?>
        <table class="booking-history-table">
            <thead>
                <tr>
                    <th><?php echo h(t('history_booking_id')); ?></th>
                    <th><?php echo h(t('history_room_name')); ?></th>
                    <th><?php echo h(t('history_check_in')); ?></th>
                    <th><?php echo h(t('history_check_out')); ?></th>
                    <th><?php echo h(t('history_price')); ?></th>
                    <th><?php echo h(t('history_status')); ?></th>
                    <th><?php echo h(t('history_action')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo h($booking['id']); ?></td>
                        <td><?php echo h($current_lang === 'en' && !empty($booking['room_name_en']) ? $booking['room_name_en'] : $booking['room_name']); ?></td>
                        <td><?php echo h($booking['check_in_date']); ?></td>
                        <td><?php echo h($booking['check_out_date']); ?></td>
                        <td>¥<?php echo h(number_format($booking['total_price'])); ?></td>
                        <td>
                            <?php if ($booking['status'] === 'confirmed'): ?>
                                <span class="status-confirmed"><?php echo h(t('status_confirmed')); ?></span>
                            <?php else: ?>
                                <span class="status-cancelled"><?php echo h(t('status_cancelled')); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($booking['status'] === 'confirmed'): ?>
                                <!-- TODO: キャンセル機能を後で実装 -->
                                <a href="cancel_booking.php?id=<?php echo h($booking['id']); ?>" onclick="return confirm('本当にこの予約をキャンセルしますか？');"><?php echo h(t('action_cancel')); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
