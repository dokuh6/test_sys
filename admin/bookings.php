<?php
require_once 'admin_check.php';

$message = '';
$error = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- ステータス更新処理 (POSTリクエスト) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token(); // CSRFチェック
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

    if ($booking_id) {
        try {
            if ($_POST['action'] === 'update_payment') {
                $status = $_POST['payment_status'] === 'paid' ? 'paid' : 'unpaid';
                $sql = "UPDATE bookings SET payment_status = :status WHERE id = :id";
                $stmt = $dbh->prepare($sql);
                $stmt->execute([':status' => $status, ':id' => $booking_id]);
                $message = "予約ID {$booking_id} の支払ステータスを更新しました。";
            } elseif ($_POST['action'] === 'update_checkin') {
                $status = $_POST['checkin_status'] === 'checked_in' ? 'checked_in' : 'waiting';
                $sql = "UPDATE bookings SET checkin_status = :status WHERE id = :id";
                $stmt = $dbh->prepare($sql);
                $stmt->execute([':status' => $status, ':id' => $booking_id]);
                $message = "予約ID {$booking_id} のチェックインステータスを更新しました。";
            }
        } catch (PDOException $e) {
            $error = "ステータス更新エラー: " . h($e->getMessage());
        }
    }
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

// --- ダッシュボード集計 ---
$today = date('Y-m-d');
$dashboard = [
    'checkin_today' => 0,
    'unpaid_confirmed' => 0
];
try {
    // 本日のチェックイン予定 (waiting status)
    $sql = "SELECT COUNT(*) FROM bookings WHERE check_in_date = :today AND status = 'confirmed' AND checkin_status = 'waiting'";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([':today' => $today]);
    $dashboard['checkin_today'] = $stmt->fetchColumn();

    // 未払い予約 (confirmed status)
    $sql = "SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND payment_status = 'unpaid'";
    $stmt = $dbh->query($sql);
    $dashboard['unpaid_confirmed'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Dashboard error ignored
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
                b.check_in_time,
                b.check_out_time,
                b.num_guests,
                b.num_children,
                b.notes,
                b.total_price,
                b.status,
                b.payment_status,
                b.checkin_status
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

<!-- Dashboard Widgets -->
<div style="display: flex; gap: 20px; margin-bottom: 20px;">
    <div style="flex: 1; background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 5px solid #2196f3;">
        <h4 style="margin: 0 0 5px 0; font-size: 0.9em; color: #1565c0;">本日のチェックイン待ち</h4>
        <span style="font-size: 1.8em; font-weight: bold; color: #0d47a1;"><?php echo h($dashboard['checkin_today']); ?>件</span>
    </div>
    <div style="flex: 1; background: #ffebee; padding: 15px; border-radius: 8px; border-left: 5px solid #f44336;">
        <h4 style="margin: 0 0 5px 0; font-size: 0.9em; color: #c62828;">未払い (確定予約)</h4>
        <span style="font-size: 1.8em; font-weight: bold; color: #b71c1c;"><?php echo h($dashboard['unpaid_confirmed']); ?>件</span>
    </div>
</div>

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

<div class="table-responsive">
<table>
    <thead>
        <tr>
            <th>予約ID</th>
            <th>顧客名</th>
            <th>部屋名</th>
            <th>チェックイン</th>
            <th>チェックアウト</th>
            <th>人数</th>
            <th>料金</th>
            <th>状況</th>
            <th>支払/入室</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($all_bookings)): ?>
            <tr>
                <td colspan="10" style="text-align: center;">予約はまだありません。</td>
            </tr>
        <?php else: ?>
            <?php foreach ($all_bookings as $booking): ?>
                <?php
                    $row_class = '';
                    if ($booking['status'] === 'cancelled') $row_class = 'row-cancelled';
                    elseif ($booking['checkin_status'] === 'checked_in') $row_class = 'row-checked-in';
                    elseif ($booking['payment_status'] === 'unpaid') $row_class = 'row-unpaid';
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><?php echo h($booking['id']); ?></td>
                    <td>
                        <?php echo h($booking['customer_name']); ?>
                        <?php if ($booking['notes']): ?>
                            <br><span style="font-size:0.8em; color:gray;">(備考あり)</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo h($booking['room_name']); ?></td>
                    <td>
                        <?php echo h($booking['check_in_date']); ?>
                        <?php if($booking['check_in_time']) echo '<br><small>' . h($booking['check_in_time']) . '</small>'; ?>
                    </td>
                    <td>
                        <?php echo h($booking['check_out_date']); ?>
                        <?php if($booking['check_out_time']) echo '<br><small>' . h($booking['check_out_time']) . '</small>'; ?>
                    </td>
                    <td>
                        大人:<?php echo h($booking['num_guests']); ?><br>
                        子供:<?php echo h($booking['num_children']); ?>
                    </td>
                    <td>¥<?php echo h(number_format($booking['total_price'])); ?></td>
                    <td>
                        <span class="status-<?php echo h($booking['status']); ?>">
                            <?php echo h($booking['status'] === 'confirmed' ? '確定' : 'キャンセル済'); ?>
                        </span>
                        <br>
                        <small>
                        <?php if($booking['payment_status'] === 'paid'): ?>
                            <span style="color:green;">支払済</span>
                        <?php else: ?>
                            <span style="color:red;">未払い</span>
                        <?php endif; ?>
                        /
                        <?php if($booking['checkin_status'] === 'checked_in'): ?>
                            <span style="color:green;">入室済</span>
                        <?php else: ?>
                            <span>待ち</span>
                        <?php endif; ?>
                        </small>
                    </td>
                    <td>
                        <form method="POST" style="display:inline-block; margin-bottom: 2px;">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="action" value="update_payment">
                            <?php if ($booking['payment_status'] !== 'paid'): ?>
                                <button type="submit" name="payment_status" value="paid" class="btn-tiny btn-paid">￥受領</button>
                            <?php else: ?>
                                <button type="submit" name="payment_status" value="unpaid" class="btn-tiny btn-undo">￥未払</button>
                            <?php endif; ?>
                        </form>
                        <br>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="action" value="update_checkin">
                            <?php if ($booking['checkin_status'] !== 'checked_in'): ?>
                                <button type="submit" name="checkin_status" value="checked_in" class="btn-tiny btn-checkin">IN済</button>
                            <?php else: ?>
                                <button type="submit" name="checkin_status" value="waiting" class="btn-tiny btn-undo">IN取消</button>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td>
                        <a href="edit_booking.php?id=<?php echo h($booking['id']); ?>" class="btn-admin" style="background-color:#3498db; font-size: 0.8em; padding: 4px 8px;">編集</a>
                        <?php if ($booking['status'] === 'confirmed'): ?>
                            <a href="bookings.php?action=cancel&id=<?php echo h($booking['id']); ?>" class="btn-admin btn-cancel" onclick="return confirm('本当にこの予約をキャンセルしますか？');" style="font-size: 0.8em; padding: 4px 8px;">
                                キャンセル
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
<style>
.status-confirmed { color: green; font-weight: bold; }
.status-cancelled { color: red; text-decoration: line-through; }
.row-cancelled { background-color: #f0f0f0; color: #999; }
.row-checked-in { background-color: #e8f5e9; } /* 薄い緑 */
.row-unpaid { background-color: #fff8e1; } /* 薄い黄色 */

.btn-tiny {
    border: none;
    border-radius: 3px;
    padding: 2px 5px;
    font-size: 0.75em;
    cursor: pointer;
    color: white;
}
.btn-paid { background-color: #4caf50; }
.btn-checkin { background-color: #2196f3; }
.btn-undo { background-color: #9e9e9e; }
</style>

<?php
require_once 'admin_footer.php';
?>
<?php
// 生成したトークンをJS等で使うわけではないが、フォーム埋め込み用に生成
$csrf_token = generate_csrf_token();
?>
<!-- フォーム内のトークン埋め込み用スクリプト (既存の各フォームに埋め込む) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var token = "<?php echo h($csrf_token); ?>";
    var forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach(function(form) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = token;
        form.appendChild(input);
    });
});
</script>
