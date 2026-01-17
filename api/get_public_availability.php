<?php
// ヘッダーをJSONに設定
header('Content-Type: application/json');

// DB接続
require_once '../includes/db_connect.php';

// セッションは不要（公開情報）だが、必要ならsession_start()
// 公開情報なので、予約者名などの個人情報は含めない。
// statusがconfirmedの予約のみを「予約済み」として返す。

try {
    // 予約データを取得
    $room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;

    $sql = "SELECT
                br.room_id AS resourceId,
                b.check_in_date AS start,
                b.check_out_date AS end
            FROM bookings AS b
            JOIN booking_rooms AS br ON b.id = br.booking_id
            WHERE b.status = 'confirmed'";

    if ($room_id) {
        $sql .= " AND br.room_id = :room_id";
    }

    $stmt = $dbh->prepare($sql);

    if ($room_id) {
        $stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($bookings as $booking) {
        $events[] = [
            'resourceId' => (string)$booking['resourceId'],
            'start' => $booking['start'],
            'end' => $booking['end'],
            'allDay' => true,
            'color' => '#ef4444', // Red-500
            'textColor' => '#ffffff',
            'title' => '予約済'
        ];
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
