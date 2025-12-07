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
    // FullCalendar Resource Timeline Viewに必要な形式に合わせる
    // event: { id, resourceId, title, start, end, color }
    $sql = "SELECT
                br.room_id AS resourceId,
                b.check_in_date AS start,
                b.check_out_date AS end
            FROM bookings AS b
            JOIN booking_rooms AS br ON b.id = br.booking_id
            WHERE b.status = 'confirmed'";

    $stmt = $dbh->prepare($sql);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($bookings as $booking) {
        $events[] = [
            'resourceId' => (string)$booking['resourceId'],
            'start' => $booking['start'],
            'end' => $booking['end'],
            'display' => 'background', // 背景色として表示（「予約済み」を視覚化）
            'color' => '#ff9f89', // 薄い赤（予約済み）
            'title' => '予約済' // 背景イベントにはタイトルは表示されにくいが、tooltip等で使えるかも
        ];
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
