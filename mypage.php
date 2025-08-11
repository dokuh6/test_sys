<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

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
                r.name AS room_name
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


require_once 'includes/header.php';
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
    <h2>マイページ</h2>
    <p>ようこそ、<?php echo h($_SESSION['user']['name']); ?>様。こちらでご自身の予約履歴をご確認いただけます。</p>

    <hr>

    <h3>ご予約履歴</h3>
    <?php if (empty($bookings)): ?>
        <p>現在、ご予約はございません。</p>
    <?php else: ?>
        <table class="booking-history-table">
            <thead>
                <tr>
                    <th>予約番号</th>
                    <th>部屋名</th>
                    <th>チェックイン</th>
                    <th>チェックアウト</th>
                    <th>料金</th>
                    <th>状況</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo h($booking['id']); ?></td>
                        <td><?php echo h($booking['room_name']); ?></td>
                        <td><?php echo h($booking['check_in_date']); ?></td>
                        <td><?php echo h($booking['check_out_date']); ?></td>
                        <td>¥<?php echo h(number_format($booking['total_price'])); ?></td>
                        <td>
                            <?php if ($booking['status'] === 'confirmed'): ?>
                                <span class="status-confirmed">確定</span>
                            <?php else: ?>
                                <span class="status-cancelled">キャンセル済</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($booking['status'] === 'confirmed'): ?>
                                <!-- TODO: キャンセル機能を後で実装 -->
                                <a href="cancel_booking.php?id=<?php echo h($booking['id']); ?>" onclick="return confirm('本当にこの予約をキャンセルしますか？');">キャンセル</a>
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
