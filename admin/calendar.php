<?php
require_once 'admin_check.php';

// 部屋リソースを取得してJSに渡す
try {
    $sql = "SELECT id, name AS title FROM rooms ORDER BY id ASC";
    $stmt = $dbh->query($sql);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // IDを文字列にキャスト（JS側での不一致防止）
    foreach ($rooms as &$room) {
        $room['id'] = (string)$room['id'];
    }
    unset($room);
} catch (PDOException $e) {
    $rooms = [];
    $error = "部屋情報の取得に失敗しました。";
}

require_once 'admin_header.php';
?>

<h2>空室カレンダー</h2>

<div id='calendar'></div>

<!-- FullCalendar Scheduler (Trial) -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.8/index.global.min.js'></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source', // オープンソース/開発用ライセンスキー
            initialView: 'resourceTimelineMonth',
            locale: 'ja',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'resourceTimelineMonth,dayGridMonth'
            },
            resourceAreaWidth: '15%',
            resourceAreaHeaderContent: '部屋',
            resources: <?php echo json_encode($rooms); ?>,
            events: 'api/calendar_events.php',
            selectable: true,
            select: function(info) {
                // 空きスロットクリック時の処理: 新規予約画面へ
                // startStrはISO文字列。book.phpに渡すためにYYYY-MM-DD形式に調整が必要な場合があるが、
                // info.startStrが "2023-10-01" のような日付文字列（時間なし）ならそのまま使える。
                // Resource Timeline Viewの場合、resourceが選択されている。
                if (info.resource) {
                    var roomId = info.resource.id;
                    var startDate = info.startStr.split('T')[0]; // 時間が含まれる場合を除去
                    // 終了日はFullCalendarでは排他的だが、book.phpではチェックアウト日として扱うため、
                    // 1泊なら翌日がendなのでそのまま渡してOK。
                    var endDate = info.endStr.split('T')[0];

                    // 宿泊人数はデフォルト1とする（後で変更可能）
                    var url = 'add_booking.php?room_id=' + roomId +
                              '&check_in=' + startDate +
                              '&check_out=' + endDate;

                    // 管理者用予約作成画面へ遷移
                    window.location.href = url;
                }
            },
            eventClick: function(info) {
                // イベントクリック時の処理（リンク遷移はeventオブジェクトのurlプロパティで自動処理されるが、ここで制御も可能）
            },
            height: 'auto',
            businessHours: true,
            nowIndicator: true
        });
        calendar.render();
    });
</script>

<style>
    #calendar {
        max-width: 100%;
        margin: 20px auto;
        background: white;
        padding: 20px;
    }
    /* FullCalendarのスタイル調整 */
    .fc-event {
        cursor: pointer;
    }
</style>

<?php
require_once 'admin_footer.php';
?>
