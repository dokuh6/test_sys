<?php
// ヘッダーをJSONに設定
header('Content-Type: application/json');

// セッション開始と認証チェック (admin_check.php のロジックを簡易的に再現またはinclude)
// APIなのでリダイレクトよりエラーレスポンスが望ましいが、既存の仕組みに乗るためinclude
require_once '../../includes/db_connect.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // 予約データを取得
    // FullCalendar Resource Timeline Viewに必要な形式に合わせる
    // event: { id, resourceId, title, start, end, color }
    $sql = "SELECT
                b.id,
                br.room_id AS resourceId,
                COALESCE(u.name, b.guest_name) AS title,
                b.check_in_date AS start,
                b.check_out_date AS end,
                b.status
            FROM bookings AS b
            LEFT JOIN users AS u ON b.user_id = u.id
            JOIN booking_rooms AS br ON b.id = br.booking_id
            WHERE b.status = 'confirmed'"; // キャンセルされた予約は表示しない、または色を変える

    $stmt = $dbh->prepare($sql);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($bookings as $booking) {
        // チェックアウト日はFullCalendarでは排他的(exclusive)なのでそのままでOKか確認
        // 通常、宿泊予約の「チェックアウト日」は10時チェックアウトならその日の朝まで占有する。
        // FullCalendarのendはその瞬間の直前まで。
        // 例: 10/1 IN, 10/2 OUT (1泊) -> start: 2023-10-01, end: 2023-10-02
        // これは視覚的に10/1の枠を埋めるので正しい。

        $events[] = [
            'id' => $booking['id'],
            'resourceId' => $booking['resourceId'],
            'title' => $booking['title'],
            'start' => $booking['check_in_date'],
            'end' => $booking['check_out_date'],
            'color' => '#3498db', // デフォルト青
            'url' => 'edit_booking.php?id=' . $booking['id']
        ];
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
