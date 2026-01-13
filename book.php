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
    $total_price = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_FLOAT);
    $guest_name = filter_input(INPUT_POST, 'guest_name');
    $guest_email = filter_input(INPUT_POST, 'guest_email', FILTER_VALIDATE_EMAIL);
    $guest_tel = filter_input(INPUT_POST, 'guest_tel');

    // 電話番号のバリデーション: 10-15桁, 数字・ハイフン・プラスのみ許可
    if ($guest_tel && !preg_match('/^[0-9+\-]{10,15}$/', $guest_tel)) {
        $guest_tel = false; // 無効な電話番号扱い
        $errors[] = "電話番号の形式が正しくありません。";
    }

    if (!$room_id || !$check_in || !$check_out || !$num_guests || !$total_price || !$guest_name || !$guest_email) {
        $errors[] = "入力情報が不完全です。";
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
                    throw new Exception("ただいまアクセスが集中しており、処理を完了できませんでした。しばらく経ってから再度お試しください。");
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
                throw new Exception("申し訳ございませんが、タッチの差で他の方が予約されました。別の日程で再度お試しください。");
            }

            // 4. 価格の再計算（セキュリティ対策: クライアント側での改ざん防止）
            $sql_price = "SELECT price FROM rooms WHERE id = :room_id";
            $stmt_price = $dbh->prepare($sql_price);
            $stmt_price->execute([':room_id' => $room_id]);
            $room_price = $stmt_price->fetchColumn();

            if ($room_price === false) {
                 throw new Exception("部屋情報の取得に失敗しました。");
            }

            $datetime1 = new DateTime($check_in);
            $datetime2 = new DateTime($check_out);
            $interval = $datetime1->diff($datetime2);
            $nights_calc = $interval->days;
            $total_price = $nights_calc * $room_price;

            // 5. bookingsテーブルへの登録
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
            if ($dbh->inTransaction()) {
                $dbh->rollBack();
            }
            $errors[] = "予約処理中にエラーが発生しました: " . h($e->getMessage());
        }
    }
} else {
    // GETリクエスト処理 (ページの初期表示)
    $room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $check_in = filter_input(INPUT_GET, 'check_in');
    $check_out = filter_input(INPUT_GET, 'check_out');
    $num_guests = filter_input(INPUT_GET, 'num_guests', FILTER_VALIDATE_INT);

    if (!$room_id || !$check_in || !$check_out || !$num_guests) {
        $errors[] = "予約情報が正しくありません。もう一度最初からやり直してください。";
    } else {
        if (strtotime($check_in) >= strtotime($check_out)) {
            $errors[] = "チェックアウト日はチェックイン日より後の日付でなければなりません。";
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
        $sql = "SELECT r.id, r.name, r.price, rt.capacity FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = :id";
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
        // ただし、動的プライシングがある場合はJS側とサーバー側でロジック共有が必要。
        // 現状は単純な price * nights なので再計算でOK。
        $calc_price = $nights * $room['price'];

        // もしPOSTされていて、total_priceが入っているなら、念のためそれを使う（割引等あった場合に対応できるようにするため）
        // しかし、セキュリティ的には再計算が正しい。今回は元のロジックがJS計算依存だったので、
        // 表示に関しては再計算値を優先し、POST時はhidden値を採用している。
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Booking Summary -->
            <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 h-fit">
                <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                    <?php echo h(t('booking_summary_title')); ?>
                </h3>
                <ul class="space-y-3 text-gray-700 dark:text-gray-300 mb-6">
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_room')); ?>:</strong> <?php echo h($room['name']); ?></li>
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_check_in')); ?>:</strong> <?php echo h($check_in); ?></li>
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_check_out')); ?>:</strong> <?php echo h($check_out); ?></li>
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_nights')); ?>:</strong> <?php echo h(t('booking_info_nights_count', $nights)); ?></li>
                    <li><strong class="font-semibold"><?php echo h(t('booking_info_guests')); ?>:</strong> <?php echo h(t('room_capacity_people', $num_guests)); ?></li>
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
                    <input type="hidden" name="total_price" value="<?php echo h($total_price); ?>">

                    <div>
                        <label for="guest_name" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_name')); ?></label>
                        <input type="text" id="guest_name" name="guest_name" value="<?php echo h($guest_name); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
                    </div>
                    <div>
                        <label for="guest_email" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_email')); ?></label>
                        <input type="email" id="guest_email" name="guest_email" value="<?php echo h($guest_email); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
                    </div>
                    <div>
                        <label for="guest_tel" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_tel')); ?></label>
                        <input type="tel" id="guest_tel" name="guest_tel" value="<?php echo h($guest_tel); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-4 rounded-md shadow transition-colors duration-200 mt-6" id="submitBtn">
                        <?php echo h(t('btn_confirm_booking')); ?>
                    </button>
                </form>
            </div>
        </div>
        <script>
            document.getElementById('bookingForm').addEventListener('submit', function() {
                var btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.innerHTML = '<?php echo h(t('processing_wait')); ?>...';
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
        </script>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
