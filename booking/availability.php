<?php
$root_path = '../';
require_once $root_path . 'includes/header.php';

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

<div class="container">
    <h2><?php echo h(t('availability_calendar_title') ?? '空室カレンダー'); ?></h2>
    <p><?php echo h(t('availability_calendar_desc') ?? 'カレンダーで空室状況を確認できます。赤色の部分は予約済みです。'); ?></p>

    <div id='calendar'></div>
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
            events: '<?php echo $root_path; ?>api/get_public_availability.php',
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

<style>
    #calendar {
        max-width: 100%;
        margin: 20px auto;
        background: white;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
</style>

<?php
require_once $root_path . 'includes/footer.php';
?>
