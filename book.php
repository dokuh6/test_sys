<?php
// Central initialization
require_once __DIR__ . '/includes/init.php';

$errors = [];
$room = null;

// Initialize variables to default or empty
$room_id = null;
$check_in = '';
$check_out = '';
$num_guests = 0;
$num_children = 0;
$check_in_time = '';
$check_out_time = '';
$notes = '';
$total_price = 0;
$guest_name = '';
$guest_email = '';
$guest_tel = '';
$nights = 0;

// --- POSTリクエスト処理 (フォームが送信された場合) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    // 1. 入力値の取得と検証
    $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $check_in = filter_input(INPUT_POST, 'check_in');
    $check_out = filter_input(INPUT_POST, 'check_out');
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $num_children = filter_input(INPUT_POST, 'num_children', FILTER_VALIDATE_INT) ?? 0;
    $check_in_time = filter_input(INPUT_POST, 'check_in_time');
    $check_out_time = filter_input(INPUT_POST, 'check_out_time');
    $notes = filter_input(INPUT_POST, 'notes');
    $total_price = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_FLOAT);
    $guest_name = filter_input(INPUT_POST, 'guest_name');
    $guest_email = filter_input(INPUT_POST, 'guest_email', FILTER_VALIDATE_EMAIL);
    $guest_tel = filter_input(INPUT_POST, 'guest_tel');

    // 電話番号のバリデーション: 10-15桁, 数字・ハイフン・プラスのみ許可
    if ($guest_tel && !preg_match('/^[0-9+\-]{10,15}$/', $guest_tel)) {
        $guest_tel = false; // 無効な電話番号扱い
        $errors[] = t('book_error_phone');
    }

    if (!$room_id || !$check_in || !$check_out || !$num_guests || !$total_price || !$guest_name || !$guest_email) {
        $errors[] = t('book_error_incomplete');
    }

    // フォームに値を戻すために変数へ代入（filter_inputで取得済みだが、falseの場合は元データを維持したい場合は$_POSTを参照すべきだが、ここでは簡易化）
    if ($guest_tel === false && isset($_POST['guest_tel'])) {
        $guest_tel = $_POST['guest_tel']; // エラー表示用に元の値を保持
    }
    if (!$guest_name && isset($_POST['guest_name'])) $guest_name = $_POST['guest_name'];

    if (empty($errors)) {
        try {
            $dbh->beginTransaction();

            // 2. 排他制御
            try {
                $sql_lock = "SELECT id FROM rooms WHERE id = :room_id FOR UPDATE";
                $stmt_lock = $dbh->prepare($sql_lock);
                $stmt_lock->execute([':room_id' => $room_id]);
            } catch (PDOException $e) {
                if ($e->getCode() == 'HY000' && strpos($e->getMessage(), '1205') !== false) {
                    throw new Exception(t('book_error_lock'));
                }
                throw $e;
            }

            // 3. 二重予約のチェック
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
                throw new Exception(t('book_error_taken'));
            }

            // 4. 価格の再計算（セキュリティ対策: クライアント側での改ざん防止）
            // 部屋ごとの個別料金を取得
            $sql_room_price = "SELECT price_adult, price_child FROM rooms WHERE id = :room_id";
            $stmt_room_price = $dbh->prepare($sql_room_price);
            $stmt_room_price->execute([':room_id' => $room_id]);
            $room_prices = $stmt_room_price->fetch(PDO::FETCH_ASSOC);

            if (!$room_prices) {
                 // フォールバック（通常ありえない）
                 $p_adult = defined('PRICE_PER_ADULT') ? PRICE_PER_ADULT : 4500;
                 $p_child = defined('PRICE_PER_CHILD') ? PRICE_PER_CHILD : 2500;
            } else {
                 $p_adult = $room_prices['price_adult'];
                 $p_child = $room_prices['price_child'];
            }

            $datetime1 = new DateTime($check_in);
            $datetime2 = new DateTime($check_out);
            $interval = $datetime1->diff($datetime2);
            $nights_calc = $interval->days;

            // num_childrenがNULLの場合は0にする
            $num_children_calc = $num_children ?? 0;
            $total_price = $nights_calc * ($num_guests * $p_adult + $num_children_calc * $p_child);

            // 5. bookingsテーブルへの登録
            $user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
            $booking_token = bin2hex(random_bytes(32));
            $booking_number = date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

            $sql_bookings = "INSERT INTO bookings (booking_token, booking_number, user_id, guest_name, guest_email, check_in_date, check_out_date, check_in_time, check_out_time, num_guests, num_children, notes, total_price, status) VALUES (:booking_token, :booking_number, :user_id, :guest_name, :guest_email, :check_in_date, :check_out_date, :check_in_time, :check_out_time, :num_guests, :num_children, :notes, :total_price, 'confirmed')";
            $stmt_bookings = $dbh->prepare($sql_bookings);
            $stmt_bookings->execute([
                ':booking_token' => $booking_token,
                ':booking_number' => $booking_number,
                ':user_id' => $user_id,
                ':guest_name' => $guest_name,
                ':guest_email' => $guest_email,
                ':check_in_date' => $check_in,
                ':check_out_date' => $check_out,
                ':check_in_time' => $check_in_time,
                ':check_out_time' => $check_out_time,
                ':num_guests' => $num_guests,
                ':num_children' => $num_children,
                ':notes' => $notes,
                ':total_price' => $total_price
            ]);
            $booking_id = $dbh->lastInsertId();

            $sql_br = "INSERT INTO booking_rooms (booking_id, room_id) VALUES (:booking_id, :room_id)";
            $stmt_br = $dbh->prepare($sql_br);
            $stmt_br->execute([':booking_id' => $booking_id, ':room_id' => $room_id]);

            $dbh->commit();

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

            ignore_user_abort(true);
            set_time_limit(0);

            send_booking_confirmation_email($booking_id, $dbh);

            try {
                $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com';
                $admin_subject = t('email_admin_subject_new', $booking_number);
                $admin_body = t('email_admin_body_new') . "\n\n";
                $admin_body .= t('booking_number') . ": " . $booking_number . "\n";
                $admin_body .= t('form_name') . ": " . $guest_name . "\n";
                $admin_body .= t('form_check_in') . ": " . $check_in . "\n";
                $admin_body .= t('form_check_out') . ": " . $check_out . "\n";
                $admin_body .= t('booking_info_total_price') . ": ¥" . number_format($total_price) . "\n";

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
            if ($dbh->inTransaction()) {
                $dbh->rollBack();
            }
            $errors[] = t('book_error_process', h($e->getMessage()));
        }
    }
} else {
    // GETリクエスト処理 (ページの初期表示)
    $room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $check_in = filter_input(INPUT_GET, 'check_in');
    $check_out = filter_input(INPUT_GET, 'check_out');
    $num_guests = filter_input(INPUT_GET, 'num_guests', FILTER_VALIDATE_INT);
    $num_children = filter_input(INPUT_GET, 'num_children', FILTER_VALIDATE_INT) ?? 0;

    if (!$room_id || !$check_in || !$check_out || !$num_guests) {
        $errors[] = t('book_error_invalid');
    } else {
        if (strtotime($check_in) >= strtotime($check_out)) {
            $errors[] = t('book_error_dates');
        }
    }

    // ユーザー情報の自動入力（ログイン時かつGET時のみ）
    if (isset($_SESSION['user']['id'])) {
        try {
            $sql_user = "SELECT name, email, phone FROM users WHERE id = :id";
            $stmt_user = $dbh->prepare($sql_user);
            $stmt_user->bindParam(':id', $_SESSION['user']['id'], PDO::PARAM_INT);
            $stmt_user->execute();
            $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($user_info) {
                $guest_name = $user_info['name'];
                $guest_email = $user_info['email'];
                $guest_tel = $user_info['phone'];
            }
        } catch (PDOException $e) {
            error_log("User fetch failed in book.php: " . $e->getMessage());
        }
    }
}

// 共通処理: 部屋情報の取得と料金計算
// エラーがあっても、部屋情報があれば表示したい（バリデーションエラー後の再表示など）
// ただし、room_idが不明な場合は表示できない
if ($room_id) {
    try {
        // price_adult, price_child を取得
        $sql = "SELECT r.id, r.name, r.price, r.price_adult, r.price_child, rt.capacity FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = :id";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':id', $room_id, PDO::PARAM_INT);
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        // 未設定の場合のフォールバック
        if ($room && (!isset($room['price_adult']) || $room['price_adult'] === null)) {
             $room['price_adult'] = defined('PRICE_PER_ADULT') ? PRICE_PER_ADULT : 4500;
        }
        if ($room && (!isset($room['price_child']) || $room['price_child'] === null)) {
             $room['price_child'] = defined('PRICE_PER_CHILD') ? PRICE_PER_CHILD : 2500;
        }

        if (!$room) {
            $errors[] = t('book_error_room_not_found');
        } elseif ($num_guests > $room['capacity']) {
            $errors[] = t('book_error_capacity', $room['capacity']);
        }

    } catch (PDOException $e) {
        $errors[] = t('error_db') . ": " . h($e->getMessage());
    }
}

// 料金・泊数の計算 (GET時またはPOSTエラー時の再計算)
if ($room && $check_in && $check_out) {
    try {
        $datetime1 = new DateTime($check_in);
        $datetime2 = new DateTime($check_out);
        $interval = $datetime1->diff($datetime2);
        $nights = $interval->days;

        // POSTで計算済みのtotal_priceがある場合はそれを使うが、
        // 改ざん防止のため基本は再計算するか、POST値を信頼するか。
        // ここでは表示用として再計算を行う（予約処理ではPOST値を使ったが、整合性のため）

        $p_adult = $room['price_adult'];
        $p_child = $room['price_child'];
        $calc_price = $nights * ($num_guests * $p_adult + ($num_children ?? 0) * $p_child);

        // もしPOSTされていて、total_priceが入っているなら、念のためそれを使う（割引等あった場合に対応できるようにするため）
        // しかし、セキュリティ的には再計算が正しい。
        // ここでは表示の整合性を取るため再計算値を表示する。
        $total_price = $calc_price;

    } catch (Exception $e) {
        // 日付形式不正など
    }
}

require_once 'includes/header.php';
?>

<?php
$csrf_token = generate_csrf_token();
?>

<div class="max-w-4xl mx-auto my-12 px-4">
    <h2 class="text-3xl font-bold mb-8 text-center text-gray-800 dark:text-white"><?php echo h(t('booking_title')); ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 p-6 rounded-lg mb-8 text-center">
            <?php foreach ($errors as $error): ?>
                <p class="mb-2 last:mb-0"><?php echo h($error); ?></p>
            <?php endforeach; ?>
            <?php if (!$room): // 部屋情報すら取得できない致命的なエラーの場合 ?>
                <div class="mt-4">
                    <a href="rooms.php" class="inline-block bg-white border border-red-300 text-red-600 hover:bg-red-50 font-bold py-2 px-6 rounded shadow transition-colors duration-200">
                        <?php echo h(t('btn_back_to_rooms')); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($room): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <!-- Booking Summary -->
            <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 h-fit">
                <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                    <?php echo h(t('booking_summary_title')); ?>
                </h3>
                <ul class="space-y-3 text-gray-700 dark:text-gray-300 mb-6">
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_room')); ?>:</strong> <?php echo h($room['name']); ?></li>
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_check_in')); ?>:</strong> <?php echo h($check_in); ?> <?php if($check_in_time) echo '(' . h($check_in_time) . ')'; ?></li>
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_check_out')); ?>:</strong> <?php echo h($check_out); ?> <?php if($check_out_time) echo '(' . h($check_out_time) . ')'; ?></li>
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_nights')); ?>:</strong> <?php echo h(t('booking_info_nights_count', $nights)); ?></li>
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_guests')); ?>:</strong> <?php echo h($num_guests); ?>名 (子供: <?php echo h($num_children); ?>名)</li>
                    <?php if ($notes): ?>
                        <li><strong class="font-semibold"><?php echo h(t('book_notes_label')); ?></strong> <?php echo nl2br(h($notes)); ?></li>
                    <?php endif; ?>
                </ul>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1"><?php echo h(t('booking_info_total_price')); ?></p>
                    <p class="text-3xl font-bold text-red-500">¥<?php echo h(number_format($total_price)); ?></p>
                </div>
            </div>

            <!-- Customer Form -->
            <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                    <?php echo h(t('booking_customer_form_title')); ?>
                </h3>
                <form action="book.php" method="POST" id="bookingForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="room_id" value="<?php echo h($room_id); ?>">
                    <input type="hidden" name="check_in" value="<?php echo h($check_in); ?>">
                    <input type="hidden" name="check_out" value="<?php echo h($check_out); ?>">
                    <input type="hidden" name="num_guests" value="<?php echo h($num_guests); ?>">
                    <input type="hidden" name="num_children" value="<?php echo h($num_children); ?>">
                    <input type="hidden" name="total_price" value="<?php echo h($total_price); ?>">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="check_in_time" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo h(t('book_check_in_time_label')); ?></label>
                            <select id="check_in_time" name="check_in_time" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]">
                                <option value=""><?php echo h(t('book_select_placeholder')); ?></option>
                                <?php for($i = 15; $i <= 22; $i++): ?>
                                    <?php $t = $i . ':00'; $sel = ($check_in_time == $t) ? 'selected' : ''; ?>
                                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                                    <?php $t = $i . ':30'; $sel = ($check_in_time == $t) ? 'selected' : ''; ?>
                                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label for="check_out_time" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo h(t('book_check_out_time_label')); ?></label>
                            <select id="check_out_time" name="check_out_time" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]">
                                <option value=""><?php echo h(t('book_select_placeholder')); ?></option>
                                <?php for($i = 6; $i <= 11; $i++): ?>
                                    <?php $t = $i . ':00'; $sel = ($check_out_time == $t) ? 'selected' : ''; ?>
                                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                                    <?php $t = $i . ':30'; $sel = ($check_out_time == $t) ? 'selected' : ''; ?>
                                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="guest_name" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_name')); ?></label>
                        <input type="text" id="guest_name" name="guest_name" value="<?php echo h($guest_name); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]">
                    </div>
                    <div>
                        <label for="guest_email" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_email')); ?></label>
                        <input type="email" id="guest_email" name="guest_email" value="<?php echo h($guest_email); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]">
                    </div>
                    <div>
                        <label for="guest_tel" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_tel')); ?></label>
                        <input type="tel" id="guest_tel" name="guest_tel" value="<?php echo h($guest_tel); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]">
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('book_notes_label')); ?></label>
                        <textarea id="notes" name="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]"><?php echo h($notes); ?></textarea>
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-4 rounded-md shadow transition-colors duration-200 mt-6 min-h-[44px]" id="submitBtn">
                        <?php echo h(t('btn_confirm_booking')); ?>
                    </button>
                </form>
            </div>
        </div>
        <script>
            document.getElementById('bookingForm').addEventListener('submit', function() {
                var btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.innerHTML = '<?php echo h(t('book_processing')); ?>';
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
        </script>

        <!-- Important Information Block -->
        <div class="bg-surface-light dark:bg-surface-dark p-8 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                <span class="material-icons text-primary dark:text-blue-400">info</span>
                <?php echo h(t('info_guide_title')); ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                 <!-- Left Column -->
                 <div class="space-y-6">
                    <!-- Pricing -->
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('info_pricing_title')); ?></h4>
                        <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 pl-2">
                            <li><?php echo h(t('info_pricing_adult')); ?></li>
                            <li><?php echo h(t('info_pricing_child')); ?></li>
                            <li class="text-sm"><?php echo h(t('info_pricing_infant')); ?></li>
                            <li class="text-sm"><?php echo h(t('info_pricing_payment')); ?></li>
                        </ul>
                    </div>
                     <!-- Check-in/out -->
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('info_checkin_title')); ?></h4>
                        <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 pl-2">
                            <li><?php echo h(t('info_checkin_in')); ?></li>
                            <li><?php echo h(t('info_checkin_out')); ?></li>
                            <li class="text-sm"><?php echo h(t('info_checkin_note')); ?></li>
                        </ul>
                    </div>
                 </div>

                 <!-- Right Column -->
                 <div class="space-y-6">
                    <!-- Booking -->
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('info_booking_title')); ?></h4>
                        <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 pl-2">
                            <li><?php echo h(t('info_booking_1')); ?></li>
                            <li><?php echo h(t('info_booking_2')); ?></li>
                            <li><?php echo h(t('info_booking_3')); ?></li>
                            <li><?php echo h(t('info_booking_4')); ?></li>
                        </ul>
                    </div>
                    <!-- Cancellation -->
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('info_cancel_title')); ?></h4>
                        <p class="text-gray-600 dark:text-gray-300 mb-2"><?php echo h(t('info_cancel_desc')); ?></p>
                        <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 pl-2">
                            <li><?php echo h(t('info_cancel_policy_1')); ?></li>
                            <li><?php echo h(t('info_cancel_policy_2')); ?></li>
                            <li><?php echo h(t('info_cancel_policy_3')); ?></li>
                            <li class="text-sm"><?php echo h(t('info_cancel_policy_note')); ?></li>
                            <li class="text-sm"><?php echo h(t('info_cancel_policy_except')); ?></li>
                        </ul>
                    </div>
                 </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
