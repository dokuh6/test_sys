<?php
// ヘッダーをJSONに設定
header('Content-Type: application/json');

// DB接続
require_once '../includes/init.php';

try {
    // 期間の指定があれば取得 (デフォルトは今日から3ヶ月)
    // FullCalendar sends ISO string for start/end, sometimes including time.
    $start = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-d');
    $end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+3 months'));

    // 全部屋数を取得
    $sql_rooms_count = "SELECT COUNT(*) FROM rooms";
    $total_rooms = $dbh->query($sql_rooms_count)->fetchColumn();

    // 指定期間内の日ごとの予約数を取得
    // 効率のため、PHP側でループ処理する（日付ごとにクエリを投げると重いので、予約データを一括取得して処理）
    // 予約データを全て取得し、PHPでカレンダー配列を埋める

    $sql_bookings = "SELECT
                b.check_in_date,
                b.check_out_date,
                br.room_id
            FROM bookings AS b
            JOIN booking_rooms AS br ON b.id = br.booking_id
            WHERE b.status = 'confirmed'
              AND b.check_in_date < :end
              AND b.check_out_date > :start";

    $stmt = $dbh->prepare($sql_bookings);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 日付ごとの予約数を集計
    $daily_booked_counts = [];

    foreach ($bookings as $booking) {
        $current_date = new DateTime($booking['check_in_date']);
        $end_date = new DateTime($booking['check_out_date']); // チェックアウト日は宿泊に含まない

        while ($current_date < $end_date) {
            $date_str = $current_date->format('Y-m-d');
            if ($date_str >= $start && $date_str < $end) {
                if (!isset($daily_booked_counts[$date_str])) {
                    $daily_booked_counts[$date_str] = 0;
                }
                $daily_booked_counts[$date_str]++;
            }
            $current_date->modify('+1 day');
        }
    }

    $events = [];
    // startからendまでの各日についてイベント生成
    $current = new DateTime($start);
    $end_dt = new DateTime($end);

    while ($current < $end_dt) {
        $date_str = $current->format('Y-m-d');
        $booked = isset($daily_booked_counts[$date_str]) ? $daily_booked_counts[$date_str] : 0;

        // 全部屋埋まっていたら「満室」
        if ($booked >= $total_rooms && $total_rooms > 0) {
            $events[] = [
                'start' => $date_str,
                'allDay' => true,
                'color' => '#ef4444', // Red-500 from Tailwind palette
                'textColor' => '#ffffff',
                'title' => t('calendar_status_full') ?? '満室',
                'classNames' => ['status-full'],
                'extendedProps' => ['status' => 'full']
            ];
        } else {
            // 空室あり (リスト表示用にイベントを追加)
            $events[] = [
                'start' => $date_str,
                'allDay' => true,
                'color' => '#22c55e', // Green-500
                'textColor' => '#ffffff',
                'title' => t('calendar_status_available') ?? '空室',
                'classNames' => ['status-available'],
                'extendedProps' => ['status' => 'available']
            ];
        }

        $current->modify('+1 day');
    }

    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
