<?php
require_once 'includes/header.php';

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
    // 電話番号のバリデーション (簡易的なチェック: 数字とハイフンのみ、10桁以上)
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

            // 2. 排他制御: 部屋レコードをロックして、同時処理を防ぐ
            // これにより、同時に同じ部屋に対して予約処理が走っても、片方が待機状態になる
            $sql_lock = "SELECT id FROM rooms WHERE id = :room_id FOR UPDATE";
            $stmt_lock = $dbh->prepare($sql_lock);
            $stmt_lock->execute([':room_id' => $room_id]);

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

            // 4. bookingsテーブルへの登録
            $user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
            // トークン生成
            $booking_token = bin2hex(random_bytes(32));
            // 予約番号生成 (YYYYMMDD-XXXXXXXX)
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

            // booking_roomsテーブルへの登録
            $sql_br = "INSERT INTO booking_rooms (booking_id, room_id) VALUES (:booking_id, :room_id)";
            $stmt_br = $dbh->prepare($sql_br);
            $stmt_br->execute([':booking_id' => $booking_id, ':room_id' => $room_id]);

            $dbh->commit();

            // 5. 完了ページへのリダイレクト (ユーザー待機時間を減らすため、メール送信前にレスポンスを返す)
            // セッションに予約IDを保存して、confirm.phpでの閲覧権限とする（後方互換性のため残す）
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['last_booking_id'] = $booking_id;
            // セッションロックを解放
            session_write_close();

            // トークン付きURLへリダイレクト
            header("Location: confirm.php?token=" . $booking_token);
            // コンテンツ長を0にして、ボディがないことを伝える
            header("Content-Length: 0");
            header("Connection: close");

            // 出力バッファをクリアしてフラッシュし、ブラウザにレスポンスを完了させる
            while (ob_get_level()) {
                ob_end_clean();
            }
            flush();

            // FastCGI環境の場合、リクエストをここで終了させる
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // --- ここからバックグラウンド処理 ---
            // ユーザーが切断してもスクリプトの実行を継続する
            ignore_user_abort(true);
            set_time_limit(0); // タイムアウト防止

            // 4. 予約確認メールの送信
            send_booking_confirmation_email($booking_id, $dbh);

            // 管理者へ通知メール送信
            // (簡易的な実装: エラーハンドリングは最小限)
            try {
                // 管理者メールアドレス
                $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com';
                $admin_subject = '【新規予約】予約が入りました (' . $booking_number . ')';
                $admin_body = "新しい予約が入りました。\n\n";
                $admin_body .= "予約番号: " . $booking_number . "\n";
                $admin_body .= "ゲスト名: " . $guest_name . "\n";
                $admin_body .= "チェックイン: " . $check_in . "\n";
                $admin_body .= "チェックアウト: " . $check_out . "\n";
                $admin_body .= "合計金額: ¥" . number_format($total_price) . "\n";

                // 新しいSMTP送信関数を使用
                send_email_smtp($admin_email, $admin_subject, $admin_body, null, null, $dbh);
            } catch (Exception $e) {
                error_log("Admin email failed: " . $e->getMessage());
            }

            exit();

        } catch (Exception $e) {
            $dbh->rollBack();
            // エラーをセッションに保存して、フォームページにリダイレクトして表示するなど、より親切なエラー処理が考えられる
            die("予約処理中にエラーが発生しました: " . h($e->getMessage()));
        }
    } else {
        // バリデーションエラーがある場合 (この実装では単純化のためdieで停止)
        die("入力エラー: " . h(implode(", ", $post_errors)));
    }
}


// --- GETリクエスト処理 (ページの初期表示) ---

// 1. URLから情報を取得・検証
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

// 2. 部屋情報を取得
if (empty($errors)) {
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

// 3. 料金計算
if (empty($errors)) {
    $datetime1 = new DateTime($check_in);
    $datetime2 = new DateTime($check_out);
    $interval = $datetime1->diff($datetime2);
    $nights = $interval->days;
    $total_price = $nights * $room['price'];
}

// 4. ユーザー情報の取得（ログイン時）
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
        // エラーが発生しても、予約処理自体は続行させる（自動入力が失敗するだけ）
        error_log("User fetch failed in book.php: " . $e->getMessage());
    }
}
?>

<?php
$csrf_token = generate_csrf_token();
?>
<style>
.booking-summary, .customer-form {
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}
.booking-summary ul { list-style: none; padding: 0; }
.booking-summary li { margin-bottom: 10px; font-size: 1.1rem; }
.total-price { font-size: 1.5rem; font-weight: bold; color: #d9534f; text-align: right; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; }
.form-group input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
</style>

<h2><?php echo h(t('booking_title')); ?></h2>

<?php if (!empty($errors)): ?>
    <div class="errors" style="color: red; border: 1px solid red; padding: 15px; margin-bottom: 20px;">
        <?php foreach ($errors as $error): ?>
            <p><?php echo h($error); ?></p>
        <?php endforeach; ?>
        <p><a href="rooms.php" class="btn"><?php echo h(t('btn_back_to_rooms')); ?></a></p>
    </div>
<?php else: ?>
    <div class="booking-summary">
        <h3><?php echo h(t('booking_summary_title')); ?></h3>
        <ul>
            <li><strong><?php echo h(t('booking_info_room')); ?>:</strong> <?php echo h($room['name']); ?></li>
            <li><strong><?php echo h(t('booking_info_check_in')); ?>:</strong> <?php echo h($check_in); ?></li>
            <li><strong><?php echo h(t('booking_info_check_out')); ?>:</strong> <?php echo h($check_out); ?></li>
            <li><strong><?php echo h(t('booking_info_nights')); ?>:</strong> <?php echo h(t('booking_info_nights_count', $nights)); ?></li>
            <li><strong><?php echo h(t('booking_info_guests')); ?>:</strong> <?php echo h(t('room_capacity_people', $num_guests)); ?></li>
        </ul>
        <hr>
        <p class="total-price"><?php echo h(t('booking_info_total_price')); ?>: ¥<?php echo h(number_format($total_price)); ?></p>
    </div>

    <div class="customer-form">
        <h3><?php echo h(t('booking_customer_form_title')); ?></h3>
        <form action="book.php" method="POST" id="bookingForm">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <!-- 予約情報をhiddenフィールドで渡す -->
            <input type="hidden" name="room_id" value="<?php echo h($room_id); ?>">
            <input type="hidden" name="check_in" value="<?php echo h($check_in); ?>">
            <input type="hidden" name="check_out" value="<?php echo h($check_out); ?>">
            <input type="hidden" name="num_guests" value="<?php echo h($num_guests); ?>">
            <input type="hidden" name="total_price" value="<?php echo h($total_price); ?>">

            <div class="form-group">
                <label for="guest_name"><?php echo h(t('form_name')); ?></label>
                <input type="text" id="guest_name" name="guest_name" value="<?php echo h($default_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="guest_email"><?php echo h(t('form_email')); ?></label>
                <input type="email" id="guest_email" name="guest_email" value="<?php echo h($default_email); ?>" required>
            </div>
            <div class="form-group">
                <label for="guest_tel"><?php echo h(t('form_tel')); ?></label>
                <input type="tel" id="guest_tel" name="guest_tel" value="<?php echo h($default_tel); ?>" required>
            </div>
            <hr>
            <button type="submit" class="btn" id="submitBtn"><?php echo h(t('btn_confirm_booking')); ?></button>
        </form>
    </div>
    <script>
        document.getElementById('bookingForm').addEventListener('submit', function() {
            var btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerText = '<?php echo h(t('processing_wait')); ?>...'; // 言語ファイルに対応させる場合、または '処理中...'
        });
    </script>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
