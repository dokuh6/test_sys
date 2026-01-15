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
            views: {
                resourceTimelineMonth: {
                    buttonText: '部屋別'
                },
                dayGridMonth: {
                    buttonText: '月別'
                }
            },
            datesSet: function(info) {
                if (info.view.type === 'resourceTimelineMonth') {
                    var now = new Date();
                    now.setHours(0, 0, 0, 0); // 時間をリセットして日付比較

                    // 表示範囲内に今日が含まれているか確認
                    if (now >= info.start && now < info.end) {
                        var year = now.getFullYear();
                        var month = (now.getMonth() + 1).toString().padStart(2, '0');
                        var day = now.getDate().toString().padStart(2, '0');
                        var dateStr = year + '-' + month + '-' + day;

                        // 今日のスロット要素を検索
                        var slot = document.querySelector('.fc-timeline-slot[data-date="' + dateStr + '"]');
                        if (slot) {
                            // 左端にスクロール
                            slot.scrollIntoView({ inline: 'start', block: 'nearest' });
                        }
                    }
                }
            },
            resourceAreaWidth: '15%',
            resourceAreaHeaderContent: '部屋',
            resources: <?php echo json_encode($rooms); ?>,
            events: 'api/calendar_events.php',
            selectable: true,
            select: function(info) {
                // 空きスロットクリック時の処理: 新規予約画面へ
                // info.startStrが "2023-10-01" のような日付文字列（時間なし）ならそのまま使える。
                var startDate = info.startStr.split('T')[0];
                var endDate = info.endStr.split('T')[0];
                var url = 'add_booking.php?check_in=' + startDate + '&check_out=' + endDate;

                // Resource Timeline Viewの場合、resourceが選択されているのでroom_idも渡す
                if (info.resource) {
                    var roomId = info.resource.id;
                    url += '&room_id=' + roomId;
                }

                // 管理者用予約作成画面へ遷移
                window.location.href = url;
            },
            // 日付枠クリック (DayGridMonthなどresourceがない場合用)
            dateClick: function(info) {
                // selectコールバックは範囲選択時に発火するが、単一日付クリックもselectとして扱われる設定(selectable: true)
                // しかし、viewによっては動作が異なる場合があるため、明示的なdateClickも検討。
                // ただしselectable: trueの場合、日付クリックはselectイベントとして処理されることが多い。
                // ここではselectで統一処理するが、もし反応しない場合はここを追加する。
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
        padding: 4px; /* タップ領域を広げる */
    }
    /* モバイル最適化 */
    @media (max-width: 768px) {
        .fc-toolbar {
            flex-direction: column;
            gap: 10px;
        }
        .fc-toolbar-title {
            font-size: 1.2em;
        }
        .fc-resource-area {
             /* 部屋名エリアの幅調整 */
        }
        /* セルの高さを確保してタップしやすく */
        .fc-timeline-slot {
            height: 44px;
        }
    }
</style>

<?php
require_once 'admin_footer.php';
?>
