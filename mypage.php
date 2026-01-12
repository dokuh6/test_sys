<?php
require_once 'includes/header.php';
$csrf_token = generate_csrf_token();

// ログインチェック
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = "マイページを閲覧するにはログインが必要です。";
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

// ユーザーの予約履歴を取得
try {
    $sql = "SELECT
                b.id,
                b.booking_number,
                b.check_in_date,
                b.check_out_date,
                b.total_price,
                b.status,
                r.name AS room_name,
                r.name_en AS room_name_en
            FROM bookings AS b
            JOIN booking_rooms AS br ON b.id = br.booking_id
            JOIN rooms AS r ON br.room_id = r.id
            WHERE b.user_id = :user_id
            ORDER BY b.check_in_date DESC";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("データベースエラー: " . h($e->getMessage()));
}
?>

<main class="max-w-[1280px] mx-auto w-full px-4 lg:px-10 py-12">
    <!-- Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="bg-green-50 border border-green-100 text-green-700 px-4 py-3 rounded-lg mb-6">
            <?php echo h($_SESSION['message']); ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-50 border border-red-100 text-red-700 px-4 py-3 rounded-lg mb-6">
            <?php echo h($_SESSION['error_message']); ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Hero / Welcome -->
    <div class="bg-white dark:bg-[#1a301d] rounded-2xl p-8 mb-8 shadow-sm border border-gray-100 dark:border-[#223a26] flex justify-between items-center relative overflow-hidden">
        <div class="relative z-10">
            <h2 class="text-2xl lg:text-3xl font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('mypage_welcome', $_SESSION['user']['name'])); ?></h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm">Member Status: <span class="text-primary font-bold">Standard Member</span></p>
        </div>
        <div class="hidden md:block relative z-10">
            <div class="size-16 bg-primary/10 rounded-full flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-4xl">person</span>
            </div>
        </div>
        <!-- Decorative bg pattern could go here -->
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Menu -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-[#1a301d] rounded-xl shadow-sm border border-gray-100 dark:border-[#223a26] overflow-hidden sticky top-24">
                <nav class="flex flex-col">
                    <a href="mypage.php" class="flex items-center gap-3 px-6 py-4 bg-primary/10 text-primary font-bold border-l-4 border-primary transition-colors">
                        <span class="material-symbols-outlined">dashboard</span>
                        Dashboard
                    </a>
                    <a href="change_password.php" class="flex items-center gap-3 px-6 py-4 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-l-4 border-transparent">
                        <span class="material-symbols-outlined">lock</span>
                        Change Password
                    </a>
                    <a href="logout.php" class="flex items-center gap-3 px-6 py-4 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors border-l-4 border-transparent">
                        <span class="material-symbols-outlined">logout</span>
                        <?php echo h(t('nav_logout')); ?>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content (Booking History) -->
        <div class="lg:col-span-3">
            <h3 class="text-xl font-bold text-[#0d1b10] dark:text-white mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">history</span>
                <?php echo h(t('mypage_history_title')); ?>
            </h3>

            <?php if (empty($bookings)): ?>
                <div class="bg-white dark:bg-[#1a301d] rounded-xl p-12 text-center border border-gray-100 dark:border-[#223a26]">
                    <span class="material-symbols-outlined text-5xl text-gray-300 mb-4">event_busy</span>
                    <p class="text-gray-500 mb-6"><?php echo h(t('mypage_no_bookings')); ?></p>
                    <a href="rooms.php" class="inline-block px-6 py-3 bg-primary text-[#0d1b10] font-bold rounded-lg hover:opacity-90 transition-opacity">
                        Find a Room
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="bg-white dark:bg-[#1a301d] rounded-xl p-6 shadow-sm border border-gray-100 dark:border-[#223a26] flex flex-col md:flex-row gap-6 transition-all hover:shadow-md">
                           <!-- Info Section -->
                           <div class="flex-grow space-y-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="text-xs font-bold text-gray-400 uppercase mb-1">
                                            <?php if (!empty($booking['booking_number'])): ?>
                                                <?php echo h($booking['booking_number']); ?>
                                            <?php else: ?>
                                                ID: <?php echo h($booking['id']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <h4 class="text-lg font-bold text-[#0d1b10] dark:text-white">
                                            <?php echo h($current_lang === 'en' && !empty($booking['room_name_en']) ? $booking['room_name_en'] : $booking['room_name']); ?>
                                        </h4>
                                    </div>
                                    <div class="md:hidden">
                                        <?php if ($booking['status'] === 'confirmed'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <?php echo h(t('status_confirmed')); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <?php echo h(t('status_cancelled')); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-lg">calendar_today</span>
                                        <span><?php echo h($booking['check_in_date']); ?> - <?php echo h($booking['check_out_date']); ?></span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-lg">payments</span>
                                        <span class="font-bold text-[#0d1b10] dark:text-white">¥<?php echo h(number_format($booking['total_price'])); ?></span>
                                    </div>
                                </div>
                           </div>

                           <!-- Action Section -->
                           <div class="flex flex-row md:flex-col justify-between items-end md:w-48 gap-3 border-t md:border-t-0 md:border-l border-gray-100 dark:border-[#2a452e] pt-4 md:pt-0 md:pl-6">
                                <div class="hidden md:block">
                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                            <span class="size-2 bg-green-500 rounded-full mr-1.5"></span>
                                            <?php echo h(t('status_confirmed')); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                            <?php echo h(t('status_cancelled')); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex gap-2 w-full justify-end">
                                    <a href="confirm.php?booking_id=<?php echo h($booking['id']); ?>" class="flex-1 md:flex-none flex items-center justify-center px-4 py-2 border border-gray-200 dark:border-[#2a452e] rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                        Details
                                    </a>
                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                        <form action="cancel_booking.php" method="POST" class="flex-1 md:flex-none">
                                            <input type="hidden" name="booking_id" value="<?php echo h($booking['id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                            <button type="submit" class="w-full flex items-center justify-center px-4 py-2 bg-red-50 text-red-600 border border-red-100 rounded-lg text-sm font-bold hover:bg-red-100 transition-colors" onclick="return confirm('本当にこの予約をキャンセルしますか？');">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                           </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
require_once 'includes/footer.php';
?>
