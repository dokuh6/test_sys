<?php
require_once 'includes/header.php';

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
                r.name_en as room_name_en,
                rt.name as type_name,
                rt.name_en as type_name_en
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
    echo "<h2>" . h(t('confirm_not_found')) . "</h2>";
    require_once 'includes/footer.php';
    exit();
}

$datetime1 = new DateTime($booking['check_in_date']);
$datetime2 = new DateTime($booking['check_out_date']);
$nights = $datetime1->diff($datetime2)->days;
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
    <h2><?php echo h(t('confirm_title')); ?></h2>
    <p><?php echo h(t('confirm_text_1')); ?></p>
    <p><?php echo h(t('confirm_text_2')); ?></p>
    <p><strong><?php echo h(t('confirm_booking_id')); ?>: <?php echo h($booking['id']); ?></strong></p>

    <div class="booking-details">
        <h3><?php echo h(t('confirm_summary_title')); ?></h3>
        <ul>
            <li><strong><?php echo h(t('form_name')); ?>:</strong> <?php echo h($booking['guest_name']); ?></li>
            <li><strong><?php echo h(t('booking_info_room')); ?>:</strong> <?php echo h($current_lang === 'en' && !empty($booking['room_name_en']) ? $booking['room_name_en'] : $booking['room_name']); ?> (<?php echo h($current_lang === 'en' && !empty($booking['type_name_en']) ? $booking['type_name_en'] : $booking['type_name']); ?>)</li>
            <li><strong><?php echo h(t('booking_info_check_in')); ?>:</strong> <?php echo h($booking['check_in_date']); ?></li>
            <li><strong><?php echo h(t('booking_info_check_out')); ?>:</strong> <?php echo h($booking['check_out_date']); ?></li>
            <li><strong><?php echo h(t('booking_info_nights')); ?>:</strong> <?php echo h(t('booking_info_nights_count', $nights)); ?></li>
            <li><strong><?php echo h(t('booking_info_guests')); ?>:</strong> <?php echo h(t('room_capacity_people', $booking['num_guests'])); ?></li>
            <li><strong><?php echo h(t('booking_info_total_price')); ?>:</strong> ¥<?php echo h(number_format($booking['total_price'])); ?></li>
        </ul>
    </div>
    <p style="margin-top: 30px;">
        <a href="index.php" class="btn"><?php echo h(t('btn_back_to_top')); ?></a>
    </p>
</div>


<?php
require_once 'includes/footer.php';
?>
