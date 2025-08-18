<?php
require_once 'admin_check.php';

$message = '';
$error = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- キャンセル処理 (GETリクエスト) ---
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $booking_id_to_cancel = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($booking_id_to_cancel) {
        try {
            // 予約ステータスを 'cancelled' に更新
            $sql = "UPDATE bookings SET status = 'cancelled' WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':id', $booking_id_to_cancel, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $message = "予約番号 " . h($booking_id_to_cancel) . " は正常にキャンセルされました。";
            } else {
                $error = "予約のキャンセルに失敗しました。";
            }
        } catch (PDOException $e) {
            $error = "データベースエラー: " . h($e->getMessage());
        }
    }
}

// --- 予約一覧の取得 ---
try {
    // ユーザー情報と部屋情報を結合して全予約を取得
    $sql = "SELECT
                b.id,
                COALESCE(u.name, b.guest_name) AS customer_name,
                r.name AS room_name,
                b.check_in_date,
                b.check_out_date,
                b.total_price,
                b.status
            FROM bookings AS b
            LEFT JOIN users AS u ON b.user_id = u.id
            JOIN booking_rooms AS br ON b.id = br.booking_id
            JOIN rooms AS r ON br.room_id = r.id
            ORDER BY b.check_in_date DESC";

    $stmt = $dbh->query($sql);
    $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "予約情報の取得に失敗しました: " . h($e->getMessage());
    $all_bookings = [];
}

require_once 'admin_header.php';
?>

<h2>予約管理</h2>

<?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p style="color: red;"><?php echo $error; ?></p>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>予約ID</th>
            <th>顧客名</th>
            <th>部屋名</th>
            <th>チェックイン</th>
            <th>チェックアウト</th>
            <th>料金</th>
            <th>ステータス</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($all_bookings)): ?>
            <tr>
                <td colspan="8" style="text-align: center;">予約はまだありません。</td>
            </tr>
        <?php else: ?>
            <?php foreach ($all_bookings as $booking): ?>
                <tr>
                    <td><?php echo h($booking['id']); ?></td>
                    <td><?php echo h($booking['customer_name']); ?></td>
                    <td><?php echo h($booking['room_name']); ?></td>
                    <td><?php echo h($booking['check_in_date']); ?></td>
                    <td><?php echo h($booking['check_out_date']); ?></td>
                    <td>¥<?php echo h(number_format($booking['total_price'])); ?></td>
                    <td>
                        <span class="status-<?php echo h($booking['status']); ?>">
                            <?php echo h($booking['status'] === 'confirmed' ? '確定' : 'キャンセル済'); ?>
                        </span>
                    </td>
                    <td>
                        <a href="edit_booking.php?id=<?php echo h($booking['id']); ?>" class="btn-admin" style="background-color:#3498db;">編集</a>
                        <?php if ($booking['status'] === 'confirmed'): ?>
                            <a href="bookings.php?action=cancel&id=<?php echo h($booking['id']); ?>" class="btn-admin btn-cancel" onclick="return confirm('本当にこの予約をキャンセルしますか？');">
                                キャンセル
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<style>
.status-confirmed { color: green; font-weight: bold; }
.status-cancelled { color: red; text-decoration: line-through; }
</style>

<?php
require_once 'admin_footer.php';
?>
