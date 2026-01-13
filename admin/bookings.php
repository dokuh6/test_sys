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
$start_date = filter_input(INPUT_GET, 'start_date');
$end_date = filter_input(INPUT_GET, 'end_date');
$keyword = filter_input(INPUT_GET, 'keyword');

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
            WHERE 1=1";

    $params = [];

    if ($start_date) {
        $sql .= " AND b.check_in_date >= :start_date";
        $params[':start_date'] = $start_date;
    }
    if ($end_date) {
        $sql .= " AND b.check_in_date <= :end_date";
        $params[':end_date'] = $end_date;
    }
    if ($keyword) {
        $sql .= " AND (b.guest_name LIKE :keyword OR u.name LIKE :keyword)";
        $params[':keyword'] = '%' . $keyword . '%';
    }

    $sql .= " ORDER BY b.check_in_date DESC";

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "予約情報の取得に失敗しました: " . h($e->getMessage());
    $all_bookings = [];
}

require_once 'admin_header.php';
?>

<h2>予約管理</h2>

<div class="actions" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <div>
            <label style="display: block; font-size: 0.9em; margin-bottom: 5px;">チェックイン日 (開始):</label>
            <input type="date" name="start_date" value="<?php echo h($start_date); ?>" style="padding: 5px;">
        </div>
        <div>
            <label style="display: block; font-size: 0.9em; margin-bottom: 5px;">チェックイン日 (終了):</label>
            <input type="date" name="end_date" value="<?php echo h($end_date); ?>" style="padding: 5px;">
        </div>
        <div>
            <label style="display: block; font-size: 0.9em; margin-bottom: 5px;">名前 (キーワード):</label>
            <input type="text" name="keyword" value="<?php echo h($keyword); ?>" placeholder="名前検索" style="padding: 5px;">
        </div>
        <div>
             <button type="submit" class="btn-admin" style="padding: 6px 15px;">検索</button>
             <a href="bookings.php" class="btn-admin" style="background: #95a5a6; padding: 6px 15px; text-decoration: none;">リセット</a>
        </div>
    </form>

    <div>
        <a href="add_booking.php" class="btn-admin" style="background-color: #27ae60; padding: 10px 20px; text-decoration: none;">+ 新規予約登録</a>
    </div>
</div>

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
