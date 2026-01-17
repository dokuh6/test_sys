<?php
// 管理者であるかどうかのチェック
require_once 'admin_check.php';

// ヘッダーの読み込み
require_once 'admin_header.php';

// --- Dashboard Logic ---

$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$dashboard_error = null;

// KPIs
$kpi_occupancy_rate = 0;
$kpi_repeater_rate = 0;
$kpi_cancellation_rate = 0;

// Graph Data
$chart_labels = [];
$chart_datasets = [];

try {
    // 1. Existing Stats
    $stmt_bookings = $dbh->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
    $confirmed_bookings_count = $stmt_bookings->fetchColumn();

    $stmt_users = $dbh->query("SELECT COUNT(*) FROM users WHERE `role` = 0");
    $users_count = $stmt_users->fetchColumn();

    $stmt_rooms = $dbh->query("SELECT COUNT(*) FROM rooms");
    $rooms_count = $stmt_rooms->fetchColumn();

    // 2. KPI: Occupancy Rate (This Month)
    // Numerator: Total occupied room-nights in current month
    // Denominator: Total rooms * Days in month
    // Note: Assuming 1 booking uses 1 room for simplicity as per current 'booking_rooms' usage in add_booking.
    // If multiple rooms per booking, we should count based on booking_rooms.

    // Total Room-Nights Available
    $days_in_month = (int)date('t');
    $total_room_nights_available = $rooms_count * $days_in_month;

    if ($total_room_nights_available > 0) {
        // Calculate occupied nights
        // Intersect booking dates with current month
        $sql_occupancy = "
            SELECT
                SUM(
                    DATEDIFF(
                        LEAST(b.check_out_date, :month_end_plus_one),
                        GREATEST(b.check_in_date, :month_start)
                    )
                ) as occupied_days
            FROM bookings b
            WHERE b.status = 'confirmed'
            AND b.check_in_date < :month_end_plus_one
            AND b.check_out_date > :month_start
        ";
        // Note: For multi-room bookings, this counts the booking duration once.
        // If we want accurate room occupancy, we should join booking_rooms.
        // Let's refine for accuracy:
        $sql_occupancy = "
            SELECT
                SUM(
                    DATEDIFF(
                        LEAST(b.check_out_date, :month_end_plus_one),
                        GREATEST(b.check_in_date, :month_start)
                    )
                ) as occupied_days
            FROM bookings b
            JOIN booking_rooms br ON b.id = br.booking_id
            WHERE b.status = 'confirmed'
            AND b.check_in_date < :month_end_plus_one
            AND b.check_out_date > :month_start
        ";

        $stmt_occupancy = $dbh->prepare($sql_occupancy);
        // month_end_plus_one needed for correct DATEDIFF (check_out is exclusive usually? No, check_out date is the day they leave)
        // DATEDIFF('2023-01-02', '2023-01-01') = 1 night. Correct.
        // Overlap logic:
        // Booking: 1st to 3rd (2 nights). Month: 1st to 31st.
        // Max(1st, 1st) = 1st. Min(3rd, Feb 1st) = 3rd. Diff = 2. Correct.
        $next_month_start = date('Y-m-d', strtotime($current_month_start . ' +1 month'));

        $stmt_occupancy->execute([
            ':month_start' => $current_month_start,
            ':month_end_plus_one' => $next_month_start
        ]);
        $occupied_nights = (int)$stmt_occupancy->fetchColumn();

        $kpi_occupancy_rate = round(($occupied_nights / $total_room_nights_available) * 100, 1);
    }

    // 3. KPI: Repeater Rate (Bookings starting this month)
    // (Bookings this month by repeat users) / (Total bookings this month)
    $sql_total_month_bookings = "SELECT COUNT(*) FROM bookings WHERE check_in_date BETWEEN :start AND :end AND status = 'confirmed'";
    $stmt_total_month = $dbh->prepare($sql_total_month_bookings);
    $stmt_total_month->execute([':start' => $current_month_start, ':end' => $current_month_end]);
    $total_month_bookings = $stmt_total_month->fetchColumn();

    if ($total_month_bookings > 0) {
        // Repeaters: Users with > 1 confirmed booking total (including past)
        // Check based on user_id first (more reliable)
        // If user_id is NULL, we could check guest_email, but let's stick to registered users for "Repeater" definition simplicity
        // OR check guest_email for non-registered.
        // Let's use user_id OR guest_email matches.

        // Simpler approach: count bookings where user has history.
        // We need to check each booking in this month.
        $sql_repeater = "
            SELECT COUNT(*)
            FROM bookings b
            WHERE b.check_in_date BETWEEN :start AND :end
            AND b.status = 'confirmed'
            AND (
                (b.user_id IS NOT NULL AND (SELECT COUNT(*) FROM bookings b2 WHERE b2.user_id = b.user_id AND b2.status='confirmed') > 1)
                OR
                (b.user_id IS NULL AND b.guest_email IS NOT NULL AND b.guest_email != '' AND (SELECT COUNT(*) FROM bookings b3 WHERE b3.guest_email = b.guest_email AND b3.status='confirmed') > 1)
            )
        ";
        $stmt_repeater = $dbh->prepare($sql_repeater);
        $stmt_repeater->execute([':start' => $current_month_start, ':end' => $current_month_end]);
        $repeater_bookings = $stmt_repeater->fetchColumn();

        $kpi_repeater_rate = round(($repeater_bookings / $total_month_bookings) * 100, 1);
    }

    // 4. KPI: Cancellation Rate (Bookings starting this month)
    // (Cancelled bookings for this month) / (Total bookings for this month including cancelled)
    $sql_all_month_bookings = "SELECT COUNT(*) FROM bookings WHERE check_in_date BETWEEN :start AND :end";
    $stmt_all_month = $dbh->prepare($sql_all_month_bookings);
    $stmt_all_month->execute([':start' => $current_month_start, ':end' => $current_month_end]);
    $all_month_bookings = $stmt_all_month->fetchColumn();

    if ($all_month_bookings > 0) {
        $sql_cancelled = "SELECT COUNT(*) FROM bookings WHERE check_in_date BETWEEN :start AND :end AND status = 'cancelled'";
        $stmt_cancelled = $dbh->prepare($sql_cancelled);
        $stmt_cancelled->execute([':start' => $current_month_start, ':end' => $current_month_end]);
        $cancelled_count = $stmt_cancelled->fetchColumn();

        $kpi_cancellation_rate = round(($cancelled_count / $all_month_bookings) * 100, 1);
    }

    // 5. Chart Data: Monthly Sales by Room Type (Last 6 Months)
    // Generate last 6 months list
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('Y-m', strtotime("-$i months"));
    }

    // Get Room Types
    $room_types = [];
    $stmt_rt = $dbh->query("SELECT id, name FROM room_types");
    while ($row = $stmt_rt->fetch(PDO::FETCH_ASSOC)) {
        $room_types[$row['id']] = $row['name'];
    }

    // Initialize dataset structure
    // datasets = { 'Room Type A': {'2023-01': 0, ...}, ... }
    $sales_data = [];
    foreach ($room_types as $id => $name) {
        $sales_data[$name] = array_fill_keys($months, 0);
    }

    // Fetch Sales
    // We join to room_types via rooms.
    // Assuming 1 booking -> 1 primary room type.
    $six_months_ago = date('Y-m-01', strtotime("-5 months"));

    $sql_sales = "
        SELECT
            DATE_FORMAT(b.check_in_date, '%Y-%m') as month,
            rt.name as rt_name,
            SUM(b.total_price) as total_sales
        FROM bookings b
        JOIN booking_rooms br ON b.id = br.booking_id
        JOIN rooms r ON br.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.status = 'confirmed'
        AND b.check_in_date >= :start_date
        GROUP BY month, rt.name
    ";
    // Note: This sums total_price for each room in booking_rooms.
    // If a booking has 1 room, it's correct.
    // If a booking has 2 rooms, it duplicates total_price?
    // Wait, total_price is on `bookings`. Join `booking_rooms` creates Cartesian product if multiple rooms.
    // Since we group by RT name, if a booking has Room A (Type 1) and Room B (Type 1), we get 2 rows, sum adds total_price twice.
    // To avoid this, we should pick ONE room type per booking (e.g. MIN(room_id)).

    $sql_sales_refined = "
        SELECT
            DATE_FORMAT(b.check_in_date, '%Y-%m') as month,
            rt.name as rt_name,
            SUM(b.total_price) as total_sales
        FROM bookings b
        JOIN (
            SELECT booking_id, MIN(room_id) as room_id FROM booking_rooms GROUP BY booking_id
        ) br_uniq ON b.id = br_uniq.booking_id
        JOIN rooms r ON br_uniq.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.status = 'confirmed'
        AND b.check_in_date >= :start_date
        GROUP BY month, rt.name
    ";

    $stmt_sales = $dbh->prepare($sql_sales_refined);
    $stmt_sales->execute([':start_date' => $six_months_ago]);
    $results = $stmt_sales->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        if (isset($sales_data[$row['rt_name']][$row['month']])) {
            $sales_data[$row['rt_name']][$row['month']] = (int)$row['total_sales'];
        }
    }

    // Prepare Chart.js data
    $chart_labels = $months;
    $colors = ['#3498db', '#e74c3c', '#2ecc71', '#f1c40f', '#9b59b6', '#34495e'];
    $ci = 0;

    foreach ($sales_data as $rt_name => $monthly_data) {
        $chart_datasets[] = [
            'label' => $rt_name,
            'data' => array_values($monthly_data),
            'borderColor' => $colors[$ci % count($colors)],
            'backgroundColor' => 'transparent',
            'borderWidth' => 2
        ];
        $ci++;
    }

} catch (PDOException $e) {
    // エラーの場合は0を表示
    $confirmed_bookings_count = 0;
    $users_count = 0;
    $rooms_count = 0;
    $dashboard_error = "統計情報の取得に失敗しました: " . h($e->getMessage());
}
?>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.stat-card {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}
.stat-card h3 {
    margin-top: 0;
    font-size: 1rem;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.stat-card .value {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 10px 0 0;
    color: #2c3e50;
}
.stat-card .unit {
    font-size: 1rem;
    color: #95a5a6;
}
.chart-container {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-top: 30px;
}
.btn-quick {
    display: inline-block;
    padding: 10px 15px;
    background-color: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    margin-right: 10px;
    margin-bottom: 10px;
}
.btn-quick:hover {
    background-color: #2980b9;
}
</style>

<h2>ダッシュボード</h2>
<p>管理画面へようこそ。現在のサイトの状況を確認できます。</p>

<?php if (isset($dashboard_error)): ?>
    <div class="alert alert-danger" style="color: red; background: #fee; padding: 10px; border-radius: 4px;"><?php echo $dashboard_error; ?></div>
<?php endif; ?>

<!-- Basic Stats -->
<div class="dashboard-grid">
    <div class="stat-card">
        <h3>確定予約数</h3>
        <div class="value"><?php echo h(number_format($confirmed_bookings_count)); ?></div>
        <div class="unit">件</div>
    </div>
    <div class="stat-card">
        <h3>顧客数</h3>
        <div class="value"><?php echo h(number_format($users_count)); ?></div>
        <div class="unit">人</div>
    </div>
    <div class="stat-card">
        <h3>総部屋数</h3>
        <div class="value"><?php echo h(number_format($rooms_count)); ?></div>
        <div class="unit">室</div>
    </div>
</div>

<!-- KPIs (Current Month) -->
<h3 style="margin-top: 40px; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px;">今月のKPI (<?php echo date('n月'); ?>)</h3>
<div class="dashboard-grid">
    <div class="stat-card">
        <h3>稼働率</h3>
        <div class="value"><?php echo h($kpi_occupancy_rate); ?><span class="unit">%</span></div>
    </div>
    <div class="stat-card">
        <h3>リピーター率</h3>
        <div class="value"><?php echo h($kpi_repeater_rate); ?><span class="unit">%</span></div>
    </div>
    <div class="stat-card">
        <h3>キャンセル率</h3>
        <div class="value"><?php echo h($kpi_cancellation_rate); ?><span class="unit">%</span></div>
    </div>
</div>

<!-- Chart -->
<div class="chart-container">
    <h3 style="margin-top: 0;">売上推移 (過去6ヶ月・部屋タイプ別)</h3>
    <canvas id="salesChart" width="400" height="150"></canvas>
</div>

<div style="margin-top: 40px;">
    <h3>クイックメニュー</h3>
    <div>
        <a href="bookings.php" class="btn-quick">予約管理</a>
        <a href="calendar.php" class="btn-quick">カレンダー</a>
        <a href="rooms.php" class="btn-quick">部屋管理</a>
        <a href="add_booking.php" class="btn-quick" style="background-color: #27ae60;">予約追加</a>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: <?php echo json_encode($chart_datasets); ?>
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index, values) {
                            return '¥' + value.toLocaleString();
                        }
                    }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
</script>

<?php
// フッターの読み込み
require_once 'admin_footer.php';
?>
