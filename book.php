<?php
require_once 'includes/header.php';

// --- POST Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    // 1. Get and Validate Inputs
    $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $check_in = filter_input(INPUT_POST, 'check_in');
    $check_out = filter_input(INPUT_POST, 'check_out');
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $total_price = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_FLOAT);
    $guest_name = filter_input(INPUT_POST, 'guest_name');
    $guest_email = filter_input(INPUT_POST, 'guest_email', FILTER_VALIDATE_EMAIL);
    // Phone validation
    $guest_tel = filter_input(INPUT_POST, 'guest_tel');
    if ($guest_tel && !preg_match('/^[0-9\-]{10,}$/', $guest_tel)) {
        $guest_tel = false;
    }

    $post_errors = [];
    if (!$room_id || !$check_in || !$check_out || !$num_guests || !$total_price || !$guest_name || !$guest_email) {
        $post_errors[] = "入力情報が不完全です。";
    }
    if (!$guest_tel) {
        $post_errors[] = "電話番号の形式が正しくありません。";
    }

    if (empty($post_errors)) {
        try {
            $dbh->beginTransaction();

            // 2. Lock Room
            $sql_lock = "SELECT id FROM rooms WHERE id = :room_id FOR UPDATE";
            $stmt_lock = $dbh->prepare($sql_lock);
            $stmt_lock->execute([':room_id' => $room_id]);

            // 3. Double Booking Check
            $sql_check = "SELECT b.id FROM bookings b
                          JOIN booking_rooms br ON b.id = br.booking_id
                          WHERE br.room_id = :room_id
                          AND b.status = 'confirmed'
                          AND (b.check_in_date < :check_out_date AND b.check_out_date > :check_in_date)";

            $stmt_check = $dbh->prepare($sql_check);
            $stmt_check->execute([
                ':room_id' => $room_id,
                ':check_in_date' => $check_in,
                ':check_out_date' => $check_out
            ]);

            if ($stmt_check->fetch()) {
                throw new Exception("申し訳ございませんが、タッチの差で他の方が予約されました。別の日程で再度お試しください。");
            }

            // 4. Insert Booking
            $user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
            $booking_token = bin2hex(random_bytes(32));
            $booking_number = date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

            $sql_bookings = "INSERT INTO bookings (booking_token, booking_number, user_id, guest_name, guest_email, check_in_date, check_out_date, num_guests, total_price, status) VALUES (:booking_token, :booking_number, :user_id, :guest_name, :guest_email, :check_in_date, :check_out_date, :num_guests, :total_price, 'confirmed')";
            $stmt_bookings = $dbh->prepare($sql_bookings);
            $stmt_bookings->execute([
                ':booking_token' => $booking_token,
                ':booking_number' => $booking_number,
                ':user_id' => $user_id,
                ':guest_name' => $guest_name,
                ':guest_email' => $guest_email,
                ':check_in_date' => $check_in,
                ':check_out_date' => $check_out,
                ':num_guests' => $num_guests,
                ':total_price' => $total_price
            ]);
            $booking_id = $dbh->lastInsertId();

            // Insert Booking Rooms
            $sql_br = "INSERT INTO booking_rooms (booking_id, room_id) VALUES (:booking_id, :room_id)";
            $stmt_br = $dbh->prepare($sql_br);
            $stmt_br->execute([':booking_id' => $booking_id, ':room_id' => $room_id]);

            $dbh->commit();

            // 5. Redirect
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['last_booking_id'] = $booking_id;
            session_write_close();

            header("Location: confirm.php?token=" . $booking_token);
            header("Content-Length: 0");
            header("Connection: close");

            while (ob_get_level()) {
                ob_end_clean();
            }
            flush();

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // --- Background Process ---
            ignore_user_abort(true);
            set_time_limit(0);

            send_booking_confirmation_email($booking_id, $dbh);

            // Admin Notification
            try {
                $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com';
                $admin_subject = '【新規予約】予約が入りました (' . $booking_number . ')';
                $admin_body = "新しい予約が入りました。\n\n";
                $admin_body .= "予約番号: " . $booking_number . "\n";
                $admin_body .= "ゲスト名: " . $guest_name . "\n";
                $admin_body .= "チェックイン: " . $check_in . "\n";
                $admin_body .= "チェックアウト: " . $check_out . "\n";
                $admin_body .= "合計金額: ¥" . number_format($total_price) . "\n";

                $admin_headers = "From: noreply@example.com\r\n";
                $admin_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                $admin_mail_result = mb_send_mail($admin_email, $admin_subject, $admin_body, $admin_headers);
                log_email_history($admin_email, $admin_subject, $admin_body, $admin_headers, $admin_mail_result ? 'success' : 'failure', $admin_mail_result ? '' : 'mb_send_mail returned false', $dbh);

            } catch (Exception $e) {
                error_log("Admin email failed: " . $e->getMessage());
                 if (isset($admin_email) && isset($admin_subject)) {
                     log_email_history($admin_email, $admin_subject, isset($admin_body) ? $admin_body : '', isset($admin_headers) ? $admin_headers : '', 'failure', $e->getMessage(), $dbh);
                }
            }

            exit();

        } catch (Exception $e) {
            $dbh->rollBack();
            die("予約処理中にエラーが発生しました: " . h($e->getMessage()));
        }
    } else {
        die("入力エラー: " . h(implode(", ", $post_errors)));
    }
}


// --- GET Request Handling ---

$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$check_in = filter_input(INPUT_GET, 'check_in');
$check_out = filter_input(INPUT_GET, 'check_out');
$num_guests = filter_input(INPUT_GET, 'num_guests', FILTER_VALIDATE_INT);

$errors = [];
if (!$room_id || !$check_in || !$check_out || !$num_guests) {
    $errors[] = "予約情報が正しくありません。もう一度最初からやり直してください。";
} else {
    if (strtotime($check_in) >= strtotime($check_out)) {
        $errors[] = "チェックアウト日はチェックイン日より後の日付でなければなりません。";
    }
}

if (empty($errors)) {
    try {
        $sql = "SELECT r.id, r.name, r.name_en, r.price, rt.capacity FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = :id";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':id', $room_id, PDO::PARAM_INT);
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            $errors[] = "指定された部屋が見つかりません。";
        } elseif ($num_guests > $room['capacity']) {
            $errors[] = "この部屋の定員は{$room['capacity']}名です。人数が超過しています。";
        }

    } catch (PDOException $e) {
        $errors[] = "データベースエラー: " . h($e->getMessage());
    }
}

if (empty($errors)) {
    $datetime1 = new DateTime($check_in);
    $datetime2 = new DateTime($check_out);
    $interval = $datetime1->diff($datetime2);
    $nights = $interval->days;
    $total_price = $nights * $room['price'];
}

$default_name = '';
$default_email = '';
$default_tel = '';

if (isset($_SESSION['user']['id'])) {
    try {
        $sql_user = "SELECT name, email, phone FROM users WHERE id = :id";
        $stmt_user = $dbh->prepare($sql_user);
        $stmt_user->bindParam(':id', $_SESSION['user']['id'], PDO::PARAM_INT);
        $stmt_user->execute();
        $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_info) {
            $default_name = $user_info['name'];
            $default_email = $user_info['email'];
            $default_tel = $user_info['phone'];
        }
    } catch (PDOException $e) {
        error_log("User fetch failed in book.php: " . $e->getMessage());
    }
}
?>

<?php
$csrf_token = generate_csrf_token();
?>

<main class="max-w-[1000px] mx-auto w-full px-4 lg:px-10 py-12">
    <h2 class="text-3xl font-extrabold text-[#0d1b10] dark:text-white mb-8 text-center"><?php echo h(t('booking_title')); ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/20 rounded-xl p-6 mb-8 text-center">
            <?php foreach ($errors as $error): ?>
                <p class="text-red-600 dark:text-red-400 font-bold mb-2"><?php echo h($error); ?></p>
            <?php endforeach; ?>
            <div class="mt-4">
                <a href="rooms.php" class="inline-block px-6 py-3 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-50 transition-colors"><?php echo h(t('btn_back_to_rooms')); ?></a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Booking Summary Card -->
            <div class="md:col-span-1 order-2 md:order-1">
                <div class="bg-white dark:bg-[#1a301d] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#223a26] sticky top-24">
                    <h3 class="text-xl font-bold text-[#0d1b10] dark:text-white mb-6 pb-4 border-b border-gray-100 dark:border-[#2a452e]"><?php echo h(t('booking_summary_title')); ?></h3>
                    <ul class="space-y-4 text-sm">
                        <li>
                            <span class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-1"><?php echo h(t('booking_info_room')); ?></span>
                            <span class="font-bold text-[#0d1b10] dark:text-white"><?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?></span>
                        </li>
                        <li>
                            <span class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-1"><?php echo h(t('booking_info_check_in')); ?></span>
                            <span class="font-bold text-[#0d1b10] dark:text-white"><?php echo h($check_in); ?></span>
                        </li>
                        <li>
                            <span class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-1"><?php echo h(t('booking_info_check_out')); ?></span>
                            <span class="font-bold text-[#0d1b10] dark:text-white"><?php echo h($check_out); ?></span>
                        </li>
                        <li>
                            <span class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-1"><?php echo h(t('booking_info_nights')); ?></span>
                            <span class="font-bold text-[#0d1b10] dark:text-white"><?php echo h(t('booking_info_nights_count', $nights)); ?></span>
                        </li>
                        <li>
                            <span class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-1"><?php echo h(t('booking_info_guests')); ?></span>
                            <span class="font-bold text-[#0d1b10] dark:text-white"><?php echo h(t('room_capacity_people', $num_guests)); ?></span>
                        </li>
                    </ul>
                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-[#2a452e] flex justify-between items-center">
                        <span class="font-bold text-[#0d1b10] dark:text-white"><?php echo h(t('booking_info_total_price')); ?></span>
                        <span class="text-2xl font-extrabold text-[#d9534f]">¥<?php echo h(number_format($total_price)); ?></span>
                    </div>
                </div>
            </div>

            <!-- Customer Form -->
            <div class="md:col-span-2 order-1 md:order-2">
                <div class="bg-white dark:bg-[#1a301d] p-8 rounded-xl shadow-sm border border-gray-100 dark:border-[#223a26]">
                    <h3 class="text-xl font-bold text-[#0d1b10] dark:text-white mb-6"><?php echo h(t('booking_customer_form_title')); ?></h3>

                    <form action="book.php" method="POST" id="bookingForm" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="room_id" value="<?php echo h($room_id); ?>">
                        <input type="hidden" name="check_in" value="<?php echo h($check_in); ?>">
                        <input type="hidden" name="check_out" value="<?php echo h($check_out); ?>">
                        <input type="hidden" name="num_guests" value="<?php echo h($num_guests); ?>">
                        <input type="hidden" name="total_price" value="<?php echo h($total_price); ?>">

                        <div>
                            <label for="guest_name" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_name')); ?> <span class="text-red-500">*</span></label>
                            <input type="text" id="guest_name" name="guest_name" value="<?php echo h($default_name); ?>" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder-gray-400" placeholder="e.g. Taro Yamada">
                        </div>

                        <div>
                            <label for="guest_email" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_email')); ?> <span class="text-red-500">*</span></label>
                            <input type="email" id="guest_email" name="guest_email" value="<?php echo h($default_email); ?>" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder-gray-400" placeholder="e.g. taro@example.com">
                        </div>

                        <div>
                            <label for="guest_tel" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_tel')); ?> <span class="text-red-500">*</span></label>
                            <input type="tel" id="guest_tel" name="guest_tel" value="<?php echo h($default_tel); ?>" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder-gray-400" placeholder="e.g. 090-1234-5678">
                        </div>

                        <div class="pt-6 border-t border-gray-100 dark:border-[#2a452e]">
                            <button type="submit" id="submitBtn" class="w-full bg-primary hover:bg-opacity-90 text-[#0d1b10] py-4 rounded-xl font-bold text-lg transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">check_circle</span>
                                <?php echo h(t('btn_confirm_booking')); ?>
                            </button>
                            <p class="text-center text-xs text-gray-500 mt-4">By clicking the button, you agree to our Terms of Service.</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('bookingForm').addEventListener('submit', function() {
                var btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> <?php echo h(t('processing_wait')); ?>...';
                btn.classList.add('opacity-70', 'cursor-not-allowed');
            });
        </script>
    <?php endif; ?>
</main>

<?php
require_once 'includes/footer.php';
?>
