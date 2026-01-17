<?php
// ヘッダーをJSONに設定
header('Content-Type: application/json');

// DB接続
require_once '../includes/init.php';

try {
    // パラメータ取得
    $room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
    $start = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-d');
    $end = isset($_GET['end']) ? substr($_GET['end'], 0, 10) : date('Y-m-d', strtotime('+3 months'));

    if (!$room_id) {
        echo json_encode([]);
        exit;
    }

    // 予約データを取得
    $sql = "SELECT
                b.check_in_date,
                b.check_out_date
            FROM bookings AS b
            JOIN booking_rooms AS br ON b.id = br.booking_id
            WHERE b.status = 'confirmed'
              AND br.room_id = :room_id
              AND b.check_in_date < :end
              AND b.check_out_date > :start";

    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':room_id' => $room_id,
        ':start' => $start,
        ':end' => $end
    ]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 予約済みの日付セットを作成
    $booked_dates = [];
    foreach ($bookings as $booking) {
        $current_date = new DateTime($booking['check_in_date']);
        $end_date = new DateTime($booking['check_out_date']);

        while ($current_date < $end_date) {
            $booked_dates[$current_date->format('Y-m-d')] = true;
            $current_date->modify('+1 day');
        }
    }

    // イベント生成ループ
    $events = [];
    $current = new DateTime($start);
    $end_dt = new DateTime($end);

    while ($current < $end_dt) {
        $date_str = $current->format('Y-m-d');

        if (isset($booked_dates[$date_str])) {
             $events[] = [
                'start' => $date_str,
                'allDay' => true,
                'color' => '#ef4444', // Red-500
                'textColor' => '#ffffff',
                'title' => t('calendar_status_reserved') ?? '予約済',
                'extendedProps' => ['status' => 'reserved']
            ];
        } else {
             $events[] = [
                'start' => $date_str,
                'allDay' => true,
                'color' => '#22c55e', // Green-500
                'textColor' => '#ffffff',
                'title' => t('calendar_status_available') ?? '空室',
                'extendedProps' => ['status' => 'available']
            ];
        }

        $current->modify('+1 day');
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
