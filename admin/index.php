<?php
// 管理者であるかどうかのチェック
require_once 'admin_check.php';

// ヘッダーの読み込み
require_once 'admin_header.php';
?>

<?php
// 統計情報を取得
try {
    // 確定済みの予約数
    $stmt_bookings = $dbh->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
    $confirmed_bookings_count = $stmt_bookings->fetchColumn();

    // 顧客数 (role=0)
    $stmt_users = $dbh->query("SELECT COUNT(*) FROM users WHERE role = 0");
    $users_count = $stmt_users->fetchColumn();

    // 部屋数
    $stmt_rooms = $dbh->query("SELECT COUNT(*) FROM rooms");
    $rooms_count = $stmt_rooms->fetchColumn();

} catch (PDOException $e) {
    // エラーの場合は0を表示
    $confirmed_bookings_count = 0;
    $users_count = 0;
    $rooms_count = 0;
    $dashboard_error = "統計情報の取得に失敗しました: " . h($e->getMessage());
}
?>

<style>
.dashboard-stats {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}
.stat-card {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    flex: 1;
    text-align: center;
}
.stat-card h3 {
    margin-top: 0;
    font-size: 1.2rem;
    color: #555;
}
.stat-card p {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 10px 0 0;
    color: #2c3e50;
}
</style>

<h2>ダッシュボード</h2>
<p>管理画面へようこそ。現在のサイトの状況を確認できます。</p>

<?php if (isset($dashboard_error)): ?>
    <p style="color: red;"><?php echo $dashboard_error; ?></p>
<?php endif; ?>

<div class="dashboard-stats">
    <div class="stat-card">
        <h3>確定済みの予約数</h3>
        <p><?php echo h($confirmed_bookings_count); ?></p>
    </div>
    <div class="stat-card">
        <h3>登録顧客数</h3>
        <p><?php echo h($users_count); ?></p>
    </div>
    <div class="stat-card">
        <h3>総部屋数</h3>
        <p><?php echo h($rooms_count); ?></p>
    </div>
</div>

<div style="margin-top: 40px;">
    <h3>クイックアクセス</h3>
    <ul>
        <li><a href="bookings.php">予約管理へ進む</a></li>
        <li><a href="rooms.php">部屋管理へ進む</a></li>
    </ul>
</div>

<?php
// フッターの読み込み
require_once 'admin_footer.php';
?>
