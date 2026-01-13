<?php
require_once 'includes/init.php';

// Initialize variables
$errors = [];
$booking = null;
$can_view = false;
$nights = 0;

// 1. URLから情報を取得
$booking_id = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);
$token = filter_input(INPUT_GET, 'token');
$booking_number = filter_input(INPUT_GET, 'booking_number');
$email = filter_input(INPUT_GET, 'guest_email'); // 追加: メールアドレスによる認証用

if (!$booking_id && !$token && !$booking_number) {
    // どちらも無い場合はトップページへ
    header('Location: index.php');
    exit();
}

// セッション開始 (init.phpで処理済み)

$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

// トークンがある場合は、トークンで予約を特定
if ($token) {
    // トークン検証はDB検索時に行う
    $search_by_token = true;
} elseif ($booking_number && $email) {
    $search_by_token = false;
    // 予約番号 + メールアドレス での認証
    // ここではフラグだけ立てて、実際のデータ照合はDBクエリ後に行う
    $search_by_number_email = true;
} else {
    $search_by_token = false;
    // booking_idのみの場合のセキュリティチェック

    // 1. 直前に予約したユーザー (セッションチェック)
    if (isset($_SESSION['last_booking_id']) && $_SESSION['last_booking_id'] == $booking_id) {
        $can_view = true;
    }
    // 2. ログイン済みユーザーで、自分の予約の場合 (後でDB照合)
    elseif (isset($user_id)) {
        // チェックロジックはデータ取得後
    }
    else {
        $errors[] = "このページを表示する権限がありません。";
    }
}

// 2. データベースから予約情報を取得 (権限エラーがない場合のみ)
if (empty($errors)) {
    try {
        $sql = "SELECT
                    b.id,
                    b.booking_number,
                    b.user_id,
                    b.guest_name,
                    b.check_in_date,
                    b.check_out_date,
                    b.num_guests,
                    b.total_price,
                    b.status,
                    r.name as room_name,
                    r.name_en as room_name_en,
                    rt.name as type_name,
                    rt.name_en as type_name_en
                FROM bookings b
                JOIN booking_rooms br ON b.id = br.booking_id
                JOIN rooms r ON br.room_id = r.id
                JOIN room_types rt ON r.room_type_id = rt.id";

        if ($search_by_token) {
            $sql .= " WHERE b.booking_token = :token";
        } elseif (isset($search_by_number_email) && $search_by_number_email) {
            $sql .= " WHERE b.booking_number = :booking_number AND b.guest_email = :email";
        } else {
            // booking_id指定、または booking_numberのみ（詳細表示不可）
            if ($booking_number) {
                 $sql .= " WHERE b.booking_number = :booking_number";
            } else {
                 $sql .= " WHERE b.id = :booking_id";
            }
        }

        $stmt = $dbh->prepare($sql);

        if ($search_by_token) {
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        } elseif (isset($search_by_number_email) && $search_by_number_email) {
            $stmt->bindParam(':booking_number', $booking_number, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        } else {
            if ($booking_number) {
                $stmt->bindParam(':booking_number', $booking_number, PDO::PARAM_STR);
            } else {
                $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        // 予約が見つかった場合の権限チェック (トークン利用時はトークン合致でOKとする = 鍵を持ってる)
        if ($booking) {
            if ($search_by_token) {
                $can_view = true;
            } elseif (isset($search_by_number_email) && $search_by_number_email) {
                // メールアドレスと予約番号が一致した場合
                $can_view = true;
            } else {
                // ID指定の場合の所有者チェック
                if (isset($user_id)) {
                    if ($booking['user_id'] == $user_id) {
                        $can_view = true;
                    } elseif (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == 1) {
                        $can_view = true; // 管理者
                    }
                }
            }
        }

        if (!$can_view && empty($errors)) {
             $errors[] = "このページを表示する権限がありません。";
        }

    } catch (PDOException $e) {
        $errors[] = "データベースエラー: " . h($e->getMessage());
    }
}

// ここからHTML出力
require_once 'includes/header.php';

// エラーがある場合
if (!empty($errors) || !$booking) {
    echo "<div class='max-w-4xl mx-auto my-12 p-8 bg-surface-light dark:bg-surface-dark rounded-xl shadow-lg text-center'>";
    if (!empty($errors)) {
         echo "<div class='bg-red-50 border border-red-200 text-red-700 p-6 rounded-lg mb-8 text-center'>";
         foreach ($errors as $error) {
             echo "<p class='mb-2 last:mb-0'>" . h($error) . "</p>";
         }
         echo "</div>";
    } else {
        echo "<h2 class='text-2xl font-bold text-gray-800 dark:text-white mb-4'>" . h(t('confirm_not_found')) . "</h2>";
    }
    echo "<a href='index.php' class='inline-block bg-primary hover:bg-primary-dark text-white font-bold py-2.5 px-6 rounded-md shadow transition-colors duration-200'>" . h(t('btn_back_to_top')) . "</a>";
    echo "</div>";
    require_once 'includes/footer.php';
    exit();
}

$datetime1 = new DateTime($booking['check_in_date']);
$datetime2 = new DateTime($booking['check_out_date']);
$nights = $datetime1->diff($datetime2)->days;
?>

<div class="max-w-2xl mx-auto my-12 px-4">
    <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <?php if ($booking['status'] === 'cancelled'): ?>
            <div class="bg-gray-100 dark:bg-gray-800 p-8 text-center border-b border-gray-200 dark:border-gray-700">
                <span class="material-icons text-6xl text-gray-500 mb-4">cancel</span>
                <h2 class="text-3xl font-bold text-gray-600 dark:text-gray-400 mb-2"><?php echo h(t('cancelled_title')); ?></h2>
                <p class="text-gray-600 dark:text-gray-300"><?php echo h(t('cancelled_text_1')); ?></p>
                <p class="text-gray-600 dark:text-gray-300 text-sm mt-1"><?php echo h(t('cancelled_text_2')); ?></p>
            </div>
        <?php else: ?>
            <div class="bg-green-50 dark:bg-green-900/30 p-8 text-center border-b border-green-100 dark:border-green-800">
                <span class="material-icons text-6xl text-green-500 mb-4">check_circle</span>
                <h2 class="text-3xl font-bold text-green-600 dark:text-green-400 mb-2"><?php echo h(t('confirm_title')); ?></h2>
                <p class="text-gray-600 dark:text-gray-300"><?php echo h(t('confirm_text_1')); ?></p>
                <p class="text-gray-600 dark:text-gray-300 text-sm mt-1"><?php echo h(t('confirm_text_2')); ?></p>
            </div>
        <?php endif; ?>

        <div class="p-8">
            <div class="text-center mb-8">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1"><?php echo h(t('confirm_booking_id')); ?></p>
                <p class="text-3xl font-bold text-gray-800 dark:text-white tracking-wider">
                    <?php if (!empty($booking['booking_number'])): ?>
                        <?php echo h($booking['booking_number']); ?>
                    <?php else: ?>
                        <?php echo h($booking['id']); ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700 pt-6">
                <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-white"><?php echo h(t('confirm_summary_title')); ?></h3>
                <ul class="space-y-4 text-gray-700 dark:text-gray-300">
                    <li class="flex border-b border-gray-100 dark:border-gray-800 pb-2">
                        <span class="w-1/3 font-semibold text-gray-500 dark:text-gray-400"><?php echo h(t('form_name')); ?></span>
                        <span class="w-2/3"><?php echo h($booking['guest_name']); ?></span>
                    </li>
                    <li class="flex border-b border-gray-100 dark:border-gray-800 pb-2">
                        <span class="w-1/3 font-semibold text-gray-500 dark:text-gray-400"><?php echo h(t('booking_info_room')); ?></span>
                        <span class="w-2/3"><?php echo h($current_lang === 'en' && !empty($booking['room_name_en']) ? $booking['room_name_en'] : $booking['room_name']); ?> <span class="text-sm text-gray-500">(<?php echo h($current_lang === 'en' && !empty($booking['type_name_en']) ? $booking['type_name_en'] : $booking['type_name']); ?>)</span></span>
                    </li>
                    <li class="flex border-b border-gray-100 dark:border-gray-800 pb-2">
                        <span class="w-1/3 font-semibold text-gray-500 dark:text-gray-400"><?php echo h(t('booking_info_check_in')); ?></span>
                        <span class="w-2/3"><?php echo h($booking['check_in_date']); ?></span>
                    </li>
                    <li class="flex border-b border-gray-100 dark:border-gray-800 pb-2">
                        <span class="w-1/3 font-semibold text-gray-500 dark:text-gray-400"><?php echo h(t('booking_info_check_out')); ?></span>
                        <span class="w-2/3"><?php echo h($booking['check_out_date']); ?></span>
                    </li>
                    <li class="flex border-b border-gray-100 dark:border-gray-800 pb-2">
                        <span class="w-1/3 font-semibold text-gray-500 dark:text-gray-400"><?php echo h(t('booking_info_nights')); ?></span>
                        <span class="w-2/3"><?php echo h(t('booking_info_nights_count', $nights)); ?></span>
                    </li>
                    <li class="flex border-b border-gray-100 dark:border-gray-800 pb-2">
                        <span class="w-1/3 font-semibold text-gray-500 dark:text-gray-400"><?php echo h(t('booking_info_guests')); ?></span>
                        <span class="w-2/3"><?php echo h(t('room_capacity_people', $booking['num_guests'])); ?></span>
                    </li>
                    <li class="flex pt-2">
                        <span class="w-1/3 font-semibold text-gray-500 dark:text-gray-400"><?php echo h(t('booking_info_total_price')); ?></span>
                        <span class="w-2/3 text-xl font-bold text-red-500">¥<?php echo h(number_format($booking['total_price'])); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-800/50 p-6 text-center border-t border-gray-100 dark:border-gray-700">
            <a href="index.php" class="inline-block bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-md shadow transition-colors duration-200">
                <?php echo h(t('btn_back_to_top')); ?>
            </a>

            <?php if (isset($booking['status']) && $booking['status'] === 'confirmed'): ?>
                <div class="mt-6">
                    <button onclick="document.getElementById('cancel-modal').classList.remove('hidden')" class="text-red-500 hover:text-red-700 underline text-sm">
                        <?php echo h(t('btn_cancel_booking') ?? '予約をキャンセルする'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div id="cancel-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
        <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-white"><?php echo h(t('cancel_confirm_title') ?? '予約キャンセルの確認'); ?></h3>
        <p class="mb-6 text-gray-600 dark:text-gray-300"><?php echo h(t('cancel_confirm_text') ?? '本当にこの予約をキャンセルしますか？この操作は取り消せません。'); ?></p>

        <form action="cancel_booking.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo h(generate_csrf_token()); ?>">
            <input type="hidden" name="booking_id" value="<?php echo h($booking['id']); ?>">
            <?php if ($token): ?>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
            <?php endif; ?>
            <?php if ($booking_number): ?>
                <input type="hidden" name="booking_number" value="<?php echo h($booking_number); ?>">
            <?php endif; ?>
            <?php if ($email): ?>
                <input type="hidden" name="email" value="<?php echo h($email); ?>">
            <?php endif; ?>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('cancel-modal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded">
                    <?php echo h(t('btn_close') ?? '閉じる'); ?>
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded">
                    <?php echo h(t('btn_execute_cancel') ?? 'キャンセルする'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
