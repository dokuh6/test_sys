<?php
require_once 'includes/header.php';

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

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$can_view = false;
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
        die("このページを表示する権限がありません。");
    }
}

// 2. データベースから予約情報を取得
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

    if (!$can_view) {
         // Tailwind styled error page
         echo "<main class='max-w-[800px] mx-auto w-full px-4 lg:px-10 py-16 text-center'>";
         echo "<h2 class='text-2xl font-bold text-red-600 mb-4'>Access Denied</h2>";
         echo "<p class='text-gray-600 mb-6'>このページを表示する権限がありません。</p>";
         echo "<a href='index.php' class='btn bg-primary text-[#0d1b10] px-6 py-3 rounded-lg font-bold'>Back to Home</a>";
         echo "</main>";
         require_once 'includes/footer.php';
         exit();
    }

} catch (PDOException $e) {
    die("データベースエラー: " . h($e->getMessage()));
}

// 予約が見つからない場合
if (!$booking) {
    header("HTTP/1.0 404 Not Found");
    echo "<main class='max-w-[800px] mx-auto w-full px-4 lg:px-10 py-16 text-center'>";
    echo "<h2 class='text-2xl font-bold text-gray-800 mb-4'>" . h(t('confirm_not_found')) . "</h2>";
    echo "<a href='index.php' class='px-6 py-3 bg-primary text-[#0d1b10] rounded-lg font-bold'>Back to Home</a>";
    echo "</main>";
    require_once 'includes/footer.php';
    exit();
}

$datetime1 = new DateTime($booking['check_in_date']);
$datetime2 = new DateTime($booking['check_out_date']);
$nights = $datetime1->diff($datetime2)->days;
?>

<main class="max-w-[800px] mx-auto w-full px-4 lg:px-10 py-16">
    <!-- Success Banner -->
    <div class="text-center mb-12">
        <div class="inline-flex items-center justify-center size-24 rounded-full bg-green-100 text-green-600 mb-6 animate-bounce">
            <span class="material-symbols-outlined text-5xl">check_circle</span>
        </div>
        <h2 class="text-3xl lg:text-4xl font-extrabold text-[#0d1b10] dark:text-white mb-4"><?php echo h(t('confirm_title')); ?></h2>
        <p class="text-gray-600 dark:text-gray-400 max-w-lg mx-auto text-lg"><?php echo h(t('confirm_text_1')); ?> <?php echo h(t('confirm_text_2')); ?></p>
    </div>

    <!-- Receipt Card -->
    <div class="bg-white dark:bg-[#1a301d] rounded-2xl shadow-xl border border-gray-100 dark:border-[#223a26] overflow-hidden">
        <div class="bg-[#f6f8f6] dark:bg-[#102213] px-8 py-6 border-b border-gray-100 dark:border-[#2a452e] flex justify-between items-center flex-wrap gap-4">
            <div>
                <span class="block text-xs uppercase font-bold text-gray-500 mb-1"><?php echo h(t('confirm_booking_id')); ?></span>
                <span class="text-xl font-mono font-bold text-[#0d1b10] dark:text-white tracking-wider">
                    <?php echo !empty($booking['booking_number']) ? h($booking['booking_number']) : h($booking['id']); ?>
                </span>
            </div>
             <div class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">verified</span>
                Confirmed
            </div>
        </div>

        <div class="p-8">
            <h3 class="text-lg font-bold text-[#0d1b10] dark:text-white mb-6 pb-2 border-b border-gray-100 dark:border-[#2a452e]"><?php echo h(t('confirm_summary_title')); ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                <div>
                    <span class="block text-xs uppercase font-bold text-gray-400 mb-1"><?php echo h(t('form_name')); ?></span>
                    <span class="text-lg font-bold text-[#0d1b10] dark:text-white"><?php echo h($booking['guest_name']); ?></span>
                </div>
                <div>
                    <span class="block text-xs uppercase font-bold text-gray-400 mb-1"><?php echo h(t('booking_info_room')); ?></span>
                    <span class="text-lg font-bold text-[#0d1b10] dark:text-white">
                        <?php echo h($current_lang === 'en' && !empty($booking['room_name_en']) ? $booking['room_name_en'] : $booking['room_name']); ?>
                    </span>
                    <span class="block text-sm text-gray-500">
                        <?php echo h($current_lang === 'en' && !empty($booking['type_name_en']) ? $booking['type_name_en'] : $booking['type_name']); ?>
                    </span>
                </div>
                <div>
                    <span class="block text-xs uppercase font-bold text-gray-400 mb-1"><?php echo h(t('booking_info_check_in')); ?></span>
                    <span class="text-lg font-bold text-[#0d1b10] dark:text-white"><?php echo h($booking['check_in_date']); ?></span>
                </div>
                <div>
                    <span class="block text-xs uppercase font-bold text-gray-400 mb-1"><?php echo h(t('booking_info_check_out')); ?></span>
                    <span class="text-lg font-bold text-[#0d1b10] dark:text-white"><?php echo h($booking['check_out_date']); ?></span>
                </div>
                <div>
                    <span class="block text-xs uppercase font-bold text-gray-400 mb-1"><?php echo h(t('booking_info_guests')); ?></span>
                    <span class="text-lg font-bold text-[#0d1b10] dark:text-white"><?php echo h(t('room_capacity_people', $booking['num_guests'])); ?></span>
                </div>
                <div>
                    <span class="block text-xs uppercase font-bold text-gray-400 mb-1"><?php echo h(t('booking_info_nights')); ?></span>
                    <span class="text-lg font-bold text-[#0d1b10] dark:text-white"><?php echo h(t('booking_info_nights_count', $nights)); ?></span>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-[#102213]/50 px-8 py-6 border-t border-gray-100 dark:border-[#2a452e] flex justify-between items-center">
            <span class="font-bold text-lg text-[#0d1b10] dark:text-white"><?php echo h(t('booking_info_total_price')); ?></span>
            <span class="text-3xl font-extrabold text-[#d9534f]">¥<?php echo h(number_format($booking['total_price'])); ?></span>
        </div>
    </div>

    <div class="mt-12 text-center">
        <a href="index.php" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-primary text-[#0d1b10] font-bold rounded-xl hover:opacity-90 transition-all shadow-lg shadow-primary/20 text-lg">
            <span class="material-symbols-outlined">home</span>
            <?php echo h(t('btn_back_to_top')); ?>
        </a>
    </div>
</main>

<?php
require_once 'includes/footer.php';
?>
