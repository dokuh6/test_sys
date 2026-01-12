<?php
require_once 'includes/header.php';

// 部屋リソースを取得してJSに渡す
try {
    $sql = "SELECT id, name AS title FROM rooms ORDER BY id ASC";
    $stmt = $dbh->query($sql);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rooms as &$room) {
        $room['id'] = (string)$room['id'];
    }
    unset($room);
} catch (PDOException $e) {
    $rooms = [];
    $error = "部屋情報の取得に失敗しました。";
}
?>

<div class="max-w-7xl mx-auto my-8 px-4">
    <div class="text-center mb-8">
        <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('availability_calendar_title') ?? '空室カレンダー'); ?></h2>
        <p class="text-gray-600 dark:text-gray-300"><?php echo h(t('availability_calendar_desc') ?? 'カレンダーで空室状況を確認できます。赤色の部分は予約済みです。'); ?></p>
    </div>

    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div id='calendar' class="text-gray-800 dark:text-white"></div>
    </div>
</div>

<!-- FullCalendar Scheduler (Trial) -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.8/index.global.min.js'></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
            initialView: 'resourceTimelineMonth',
            locale: '<?php echo $current_lang ?? "ja"; ?>',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'resourceTimelineMonth'
            },
            resourceAreaWidth: '20%',
            resourceAreaHeaderContent: '<?php echo h(t('room') ?? "部屋"); ?>',
            resources: <?php echo json_encode($rooms); ?>,
            events: 'api/get_public_availability.php',
            height: 'auto',
            businessHours: true,
            nowIndicator: true,
            eventClick: function(info) {
                // 公開カレンダーなのでクリックしても何もしない、または「予約済み」と表示
            },
            select: function(info) {
                 // 空き場所をクリックしたら予約ページへ誘導することも可能
                 // 今回は表示のみとする
            }
        });
        calendar.render();
    });
</script>

<?php
require_once 'includes/footer.php';
?>
