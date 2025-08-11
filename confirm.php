<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// 1. URLから予約IDを取得
$booking_id = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);
if (!$booking_id) {
    // IDが無効な場合はトップページへ
    header('Location: index.php');
    exit();
}

// 2. データベースから予約情報を取得
try {
    $sql = "SELECT
                b.id,
                b.guest_name,
                b.check_in_date,
                b.check_out_date,
                b.num_guests,
                b.total_price,
                r.name as room_name,
                rt.name as type_name
            FROM bookings b
            JOIN booking_rooms br ON b.id = br.booking_id
            JOIN rooms r ON br.room_id = r.id
            JOIN room_types rt ON r.room_type_id = rt.id
            WHERE b.id = :booking_id";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("データベースエラー: " . h($e->getMessage()));
}

// 予約が見つからない場合
if (!$booking) {
    header("HTTP/1.0 404 Not Found");
    require_once 'includes/header.php';
    echo "<h2>指定された予約は見つかりませんでした</h2>";
    require_once 'includes/footer.php';
    exit();
}

$datetime1 = new DateTime($booking['check_in_date']);
$datetime2 = new DateTime($booking['check_out_date']);
$nights = $datetime1->diff($datetime2)->days;


require_once 'includes/header.php';
?>
<style>
.confirmation-container {
    max-width: 700px;
    margin: 20px auto;
    padding: 30px;
    text-align: center;
    border: 2px solid #28a745;
    border-radius: 10px;
    background-color: #f8f9fa;
}
.confirmation-container h2 {
    color: #28a745;
}
.booking-details {
    text-align: left;
    margin-top: 30px;
    padding: 20px;
    border-top: 1px solid #ddd;
}
.booking-details ul {
    list-style: none;
    padding: 0;
}
.booking-details li {
    margin-bottom: 12px;
    font-size: 1.1rem;
}
</style>

<div class="confirmation-container">
    <h2>ご予約ありがとうございました！</h2>
    <p>お客様のご予約が正常に完了いたしました。</p>
    <p>ご予約内容の詳細は下記をご確認ください。</p>
    <p><strong>予約番号: <?php echo h($booking['id']); ?></strong></p>

    <div class="booking-details">
        <h3>ご予約内容</h3>
        <ul>
            <li><strong>お名前:</strong> <?php echo h($booking['guest_name']); ?></li>
            <li><strong>お部屋:</strong> <?php echo h($booking['room_name']); ?> (<?php echo h($booking['type_name']); ?>)</li>
            <li><strong>チェックイン:</strong> <?php echo h($booking['check_in_date']); ?></li>
            <li><strong>チェックアウト:</strong> <?php echo h($booking['check_out_date']); ?></li>
            <li><strong>宿泊日数:</strong> <?php echo h($nights); ?>泊</li>
            <li><strong>ご利用人数:</strong> <?php echo h($booking['num_guests']); ?>名様</li>
            <li><strong>合計金額:</strong> ¥<?php echo h(number_format($booking['total_price'])); ?></li>
        </ul>
    </div>
    <p style="margin-top: 30px;">
        <a href="index.php" class="btn">トップページに戻る</a>
    </p>
</div>


<?php
require_once 'includes/footer.php';
?>
