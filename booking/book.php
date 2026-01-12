<?php
$root_path = '../';
require_once $root_path . 'includes/header.php';

// 部屋ID、日付、人数の取得
$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$check_in = filter_input(INPUT_GET, 'check_in');
$check_out = filter_input(INPUT_GET, 'check_out');
$num_guests = filter_input(INPUT_GET, 'num_guests', FILTER_VALIDATE_INT);

// 部屋情報の取得
if ($room_id) {
    $stmt = $dbh->prepare("SELECT r.*, rt.name as type_name, rt.name_en as type_name_en FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = :id");
    $stmt->execute([':id' => $room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 金額計算 (GETリクエスト時)
$total_price_display = 0;
if ($room && $check_in && $check_out) {
    $days = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
    if ($days > 0) {
        $total_price_display = $room['price'] * $days;
    }
}

// ユーザー情報（ログイン時）
$user_name = '';
$user_email = '';
$user_phone = '';
if (isset($_SESSION['user'])) {
    // セッションに保存された情報は古い可能性があるため、DBから最新の情報を取得
    $stmt = $dbh->prepare("SELECT name, email, phone FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user']['id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_name = $user_data['name'];
        $user_email = $user_data['email'];
        $user_phone = $user_data['phone'];
    }
}

// POST処理（予約実行）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    // 入力データの取得
    $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $check_in_date = filter_input(INPUT_POST, 'check_in_date');
    $check_out_date = filter_input(INPUT_POST, 'check_out_date');
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $guest_name = filter_input(INPUT_POST, 'guest_name');
    $guest_email = filter_input(INPUT_POST, 'guest_email', FILTER_VALIDATE_EMAIL);
    $guest_phone = filter_input(INPUT_POST, 'guest_phone');

    // ログインユーザーIDの取得
    $user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

    $errors = [];

    // バリデーション
    if (!$room_id || !$check_in_date || !$check_out_date || !$num_guests || !$guest_name || !$guest_email) {
        $errors[] = t('error_all_fields_required');
    }

    // 予約可能かチェック
    if (empty($errors)) {
        // 1. 部屋情報の取得
        $stmt = $dbh->prepare("SELECT price FROM rooms WHERE id = :id");
        $stmt->execute([':id' => $room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            $errors[] = t('error_room_not_found');
        } else {
            // トランザクション開始
            try {
                $dbh->beginTransaction();

                // 2. 空室チェック (FOR UPDATE でロック)
                $stmt_lock = $dbh->prepare("SELECT id FROM rooms WHERE id = :id FOR UPDATE");
                $stmt_lock->execute([':id' => $room_id]);

                // 重複チェック
                $sql = "SELECT count(*) FROM bookings b
                        JOIN booking_rooms br ON b.id = br.booking_id
                        WHERE br.room_id = :room_id
                        AND b.status != 'cancelled'
                        AND (
                            (b.check_in_date < :check_out_date AND b.check_out_date > :check_in_date)
                        )";
                $stmt = $dbh->prepare($sql);
                $stmt->execute([
                    ':room_id' => $room_id,
                    ':check_in_date' => $check_in_date,
                    ':check_out_date' => $check_out_date
                ]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = t('error_room_not_available');
                    $dbh->rollBack();
                }
            } catch (Exception $e) {
                $dbh->rollBack();
                error_log("Locking failed: " . $e->getMessage());
                $errors[] = t('error_system');
            }
        }
    }

    if (empty($errors)) {
        // 金額計算
        $days = (strtotime($check_out_date) - strtotime($check_in_date)) / (60 * 60 * 24);
        $total_price = $room['price'] * $days;

        // 予約番号生成 (YYYYMMDD-XXXXXXXX)
        $booking_number = date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        try {
            $sql = "INSERT INTO bookings (user_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, num_guests, total_price, status, booking_number, created_at)
                    VALUES (:user_id, :name, :email, :phone, :in_date, :out_date, :num, :price, 'confirmed', :booking_number, NOW())";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':name' => $guest_name,
                ':email' => $guest_email,
                ':phone' => $guest_phone,
                ':in_date' => $check_in_date,
                ':out_date' => $check_out_date,
                ':num' => $num_guests,
                ':price' => $total_price,
                ':booking_number' => $booking_number
            ]);
            $booking_id = $dbh->lastInsertId();

            $sql = "INSERT INTO booking_rooms (booking_id, room_id) VALUES (:booking_id, :room_id)";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                ':booking_id' => $booking_id,
                ':room_id' => $room_id
            ]);

            $dbh->commit();

            // セッションに予約番号を保存して、confirm.phpで検証できるようにする
            $_SESSION['last_booking_number'] = $booking_number;

            // メール送信
            $admin_subject = "【予約通知】新規予約が入りました";
            $admin_body = "新しい予約がありました。\n\n";
            $admin_body .= "予約番号: $booking_number\n";
            $admin_body .= "氏名: $guest_name\n";
            $admin_body .= "チェックイン: $check_in_date\n";
            $admin_body .= "チェックアウト: $check_out_date\n";
            send_email_smtp(ADMIN_EMAIL, $admin_subject, $admin_body, $dbh);

            send_booking_confirmation_email($booking_id, $dbh);

            // リダイレクト (PRG)
            header("Location: confirm.php?booking_number=" . urlencode($booking_number));
            exit();

        } catch (Exception $e) {
            if ($dbh->inTransaction()) {
                $dbh->rollBack();
            }
            error_log("Booking failed: " . $e->getMessage());
            $errors[] = t('error_system');
        }
    }
}

$csrf_token = generate_csrf_token();
?>

<style>
.form-container {
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
.btn {
    display: inline-block;
    padding: 10px 20px;
    background-color: #5cb85c;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}
.btn:hover { background-color: #4cae4c; }
.room-summary { background: #fff; padding: 15px; border: 1px solid #eee; margin-bottom: 20px; }
</style>

<div class="form-container">
    <h2><?php echo h(t('book_title')); ?></h2>

    <?php if (!empty($errors)): ?>
        <div style="color: red; border: 1px solid red; padding: 15px; margin-bottom: 20px;">
            <?php foreach ($errors as $error): ?>
                <p><?php echo h($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($room): ?>
        <div class="room-summary">
            <h3><?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?></h3>
            <p><?php echo h($current_lang === 'en' && !empty($room['type_name_en']) ? $room['type_name_en'] : $room['type_name']); ?></p>
            <p><?php echo h(t('room_price_per_night', number_format($room['price']))); ?></p>
            <?php if ($total_price_display > 0): ?>
                <p style="font-weight: bold; font-size: 1.1em; color: #d9534f;"><?php echo h(t('total_price')); ?>: ¥<?php echo number_format($total_price_display); ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p style="color: red;"><?php echo h(t('error_room_not_found')); ?></p>
    <?php endif; ?>

    <form action="" method="POST" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="room_id" value="<?php echo h($room_id); ?>">

        <div class="form-group">
            <label for="check_in_date"><?php echo h(t('form_check_in')); ?></label>
            <input type="date" id="check_in_date" name="check_in_date" value="<?php echo h($check_in); ?>" required>
        </div>
        <div class="form-group">
            <label for="check_out_date"><?php echo h(t('form_check_out')); ?></label>
            <input type="date" id="check_out_date" name="check_out_date" value="<?php echo h($check_out); ?>" required>
        </div>
        <div class="form-group">
            <label for="num_guests"><?php echo h(t('form_num_guests')); ?></label>
            <input type="number" id="num_guests" name="num_guests" value="<?php echo h($num_guests); ?>" min="1" required>
        </div>

        <hr>

        <div class="form-group">
            <label for="guest_name"><?php echo h(t('form_name')); ?></label>
            <input type="text" id="guest_name" name="guest_name" value="<?php echo h($user_name); ?>" required>
        </div>
        <div class="form-group">
            <label for="guest_email"><?php echo h(t('form_email')); ?></label>
            <input type="email" id="guest_email" name="guest_email" value="<?php echo h($user_email); ?>" required>
        </div>
        <div class="form-group">
            <label for="guest_phone"><?php echo h(t('form_tel')); ?></label>
            <input type="tel" id="guest_phone" name="guest_phone" value="<?php echo h($user_phone); ?>">
        </div>

        <button type="submit" class="btn"><?php echo h(t('btn_confirm')); ?></button>
    </form>
</div>
<script>
    document.getElementById('bookingForm').addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        btn.textContent = '<?php echo h(t("processing_wait")); ?>';
    });
</script>

<?php
require_once $root_path . 'includes/footer.php';
?>
