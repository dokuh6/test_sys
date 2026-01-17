<?php
// ヘッダーをJSONに設定
header('Content-Type: application/json');

// セッション開始と認証チェック (init.phpでDB接続も含む)
require_once '../../includes/init.php';

// 管理者権限チェック
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != ROLE_MANAGER) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // 予約データを取得
    $sql = "SELECT
                b.id,
                br.room_id AS resourceId,
                COALESCE(u.name, b.guest_name) AS title,
                b.check_in_date,
                b.check_out_date,
                b.status
            FROM bookings AS b
            LEFT JOIN users AS u ON b.user_id = u.id
            JOIN booking_rooms AS br ON b.id = br.booking_id
            WHERE b.status = 'confirmed'";

    $stmt = $dbh->prepare($sql);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($bookings as $booking) {
        $events[] = [
            'id' => (string)$booking['id'],
            'resourceId' => (string)$booking['resourceId'],
            'title' => $booking['title'],
            'start' => $booking['check_in_date'],
            'end' => $booking['check_out_date'],
            'color' => '#3498db',
            'url' => 'edit_booking.php?id=' . $booking['id']
        ];
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
