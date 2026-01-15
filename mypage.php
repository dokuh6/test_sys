<?php
require_once 'includes/init.php';

// ログインチェック
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = "マイページを閲覧するにはログインが必要です。";
    header('Location: login.php');
    exit();
}

require_once 'includes/header.php';
$csrf_token = generate_csrf_token();

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

<div class="max-w-6xl mx-auto my-12 bg-surface-light dark:bg-surface-dark p-8 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo h(t('mypage_title')); ?></h2>
        <p class="text-gray-600 dark:text-gray-300 mt-2 md:mt-0"><?php echo h(t('mypage_welcome', $_SESSION['user']['name'])); ?></p>
    </div>

    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="mb-6 p-4 rounded bg-green-50 text-green-700 border border-green-200">' . h($_SESSION['message']) . '</div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="mb-6 p-4 rounded bg-red-50 text-red-700 border border-red-200">' . h($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <div class="mb-8">
        <a href="change_password.php" class="inline-block border border-primary text-primary hover:bg-primary hover:text-white px-4 py-2 rounded transition-colors text-sm font-semibold">
            パスワード変更
        </a>
    </div>

    <hr class="border-gray-200 dark:border-gray-700 mb-8">

    <h3 class="text-xl font-bold mb-6 text-gray-800 dark:text-white"><?php echo h(t('mypage_history_title')); ?></h3>

    <?php if (empty($bookings)): ?>
        <p class="text-gray-600 dark:text-gray-400"><?php echo h(t('mypage_no_bookings')); ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-sm uppercase leading-normal">
                        <th class="py-3 px-6 font-bold"><?php echo h(t('history_booking_id')); ?></th>
                        <th class="py-3 px-6 font-bold"><?php echo h(t('history_room_name')); ?></th>
                        <th class="py-3 px-6 font-bold"><?php echo h(t('history_check_in')); ?></th>
                        <th class="py-3 px-6 font-bold"><?php echo h(t('history_check_out')); ?></th>
                        <th class="py-3 px-6 font-bold"><?php echo h(t('history_price')); ?></th>
                        <th class="py-3 px-6 font-bold"><?php echo h(t('history_status')); ?></th>
                        <th class="py-3 px-6 font-bold"><?php echo h(t('history_action')); ?></th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 dark:text-gray-300 text-sm font-light">
                    <?php foreach ($bookings as $booking): ?>
                        <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="py-3 px-6 whitespace-nowrap font-medium">
                                <?php if (!empty($booking['booking_number'])): ?>
                                    <?php echo h($booking['booking_number']); ?>
                                <?php else: ?>
                                    <?php echo h($booking['id']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-6">
                                <?php echo h($current_lang === 'en' && !empty($booking['room_name_en']) ? $booking['room_name_en'] : $booking['room_name']); ?>
                            </td>
                            <td class="py-3 px-6"><?php echo h($booking['check_in_date']); ?></td>
                            <td class="py-3 px-6"><?php echo h($booking['check_out_date']); ?></td>
                            <td class="py-3 px-6">¥<?php echo h(number_format($booking['total_price'])); ?></td>
                            <td class="py-3 px-6">
                                <?php if ($booking['status'] === 'confirmed'): ?>
                                    <span class="bg-green-200 text-green-700 py-1 px-3 rounded-full text-xs font-bold"><?php echo h(t('status_confirmed')); ?></span>
                                <?php else: ?>
                                    <span class="bg-red-200 text-red-700 py-1 px-3 rounded-full text-xs line-through font-bold"><?php echo h(t('status_cancelled')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-6">
                                <?php if ($booking['status'] === 'confirmed'): ?>
                                    <div class="flex flex-col gap-1">
                                        <a href="edit_booking.php?booking_id=<?php echo h($booking['id']); ?>" class="text-primary hover:text-primary-dark hover:underline font-semibold text-sm">
                                            変更
                                        </a>
                                        <form action="cancel_booking.php" method="POST" class="inline">
                                            <input type="hidden" name="booking_id" value="<?php echo h($booking['id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 hover:underline font-semibold text-sm" onclick="return confirm('本当にこの予約をキャンセルしますか？');">
                                                <?php echo h(t('action_cancel')); ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
