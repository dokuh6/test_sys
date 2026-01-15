<?php
require_once 'admin_check.php';
require_once '../includes/config.php'; // 料金定数などのため

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: bookings.php');
    exit();
}

// --- 更新処理 (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $guest_name = filter_input(INPUT_POST, 'guest_name');
    $guest_email = filter_input(INPUT_POST, 'guest_email', FILTER_VALIDATE_EMAIL);
    $guest_phone = filter_input(INPUT_POST, 'guest_phone'); // 新規追加
    $check_in_date = filter_input(INPUT_POST, 'check_in_date');
    $check_out_date = filter_input(INPUT_POST, 'check_out_date');
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $num_children = filter_input(INPUT_POST, 'num_children', FILTER_VALIDATE_INT) ?? 0;
    $check_in_time = filter_input(INPUT_POST, 'check_in_time');
    $check_out_time = filter_input(INPUT_POST, 'check_out_time');
    $notes = filter_input(INPUT_POST, 'notes');
    $status = filter_input(INPUT_POST, 'status');
    $total_price = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_INT);

    if ($guest_name && $check_in_date && $check_out_date && $num_guests && $status && $total_price) {
        if (strtotime($check_in_date) >= strtotime($check_out_date)) {
            $error = "チェックアウト日はチェックイン日より後の日付にしてください。";
        } else {
            try {
                // 重複チェック (自分自身 $id は除外)
                // まず、この予約が紐づいている部屋IDを取得
                $stmt_room = $dbh->prepare("SELECT room_id FROM booking_rooms WHERE booking_id = :id");
                $stmt_room->execute([':id' => $id]);
                $room_row = $stmt_room->fetch(PDO::FETCH_ASSOC);

                if ($room_row) {
                    $room_id = $room_row['room_id'];
                    $sql_check = "SELECT b.id FROM bookings b
                                  JOIN booking_rooms br ON b.id = br.booking_id
                                  WHERE br.room_id = :room_id
                                  AND b.status = 'confirmed'
                                  AND b.id != :current_id
                                  AND (b.check_in_date < :check_out_date AND b.check_out_date > :check_in_date)";
                    $stmt_check = $dbh->prepare($sql_check);
                    $stmt_check->execute([
                        ':room_id' => $room_id,
                        ':current_id' => $id,
                        ':check_in_date' => $check_in_date,
                        ':check_out_date' => $check_out_date
                    ]);

                    if ($stmt_check->fetch()) {
                        throw new Exception("指定された期間は既に他の予約が入っています。");
                    }
                }

                $sql = "UPDATE bookings SET
                            guest_name = :guest_name,
                            guest_email = :guest_email,
                            guest_phone = :guest_phone,
                            check_in_date = :check_in_date,
                            check_out_date = :check_out_date,
                            num_guests = :num_guests,
                            num_children = :num_children,
                            check_in_time = :check_in_time,
                            check_out_time = :check_out_time,
                            notes = :notes,
                            total_price = :total_price,
                            status = :status
                        WHERE id = :id";
                $stmt = $dbh->prepare($sql);
                $stmt->execute([
                    ':guest_name' => $guest_name,
                    ':guest_email' => $guest_email ? $guest_email : '',
                    ':guest_phone' => $guest_phone ? $guest_phone : '',
                    ':check_in_date' => $check_in_date,
                    ':check_out_date' => $check_out_date,
                    ':num_guests' => $num_guests,
                    ':num_children' => $num_children,
                    ':check_in_time' => $check_in_time,
                    ':check_out_time' => $check_out_time,
                    ':notes' => $notes,
                    ':total_price' => $total_price,
                    ':status' => $status,
                    ':id' => $id
                ]);
            // メール送信
            require_once '../includes/functions.php';
            send_booking_modification_email($id, $dbh);

                $_SESSION['message'] = "予約ID: " . h($id) . " の情報を更新しました。";
                header('Location: bookings.php');
                exit();
            } catch (PDOException $e) {
                // guest_phoneカラムがない場合のエラーハンドリングなどを考慮
                $error = "更新に失敗しました: " . h($e->getMessage());
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        $error = "必須項目 (氏名, 日程, 人数, 合計金額, ステータス) を入力してください。";
    }
}

// --- データ表示 (GET) ---
try {
    // 編集対象の予約情報を取得 (User情報も結合して、電話番号がない場合のフォールバックに使用)
    $sql = "SELECT b.*, u.phone as user_phone, u.name as user_name, u.email as user_email
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.id = :id";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header('Location: bookings.php');
        exit();
    }
} catch (PDOException $e) {
    die("情報の取得に失敗しました: " . h($e->getMessage()));
}

$csrf_token = generate_csrf_token();
require_once 'admin_header.php';
?>
<style>
.edit-form { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 800px; }
.edit-form h3 { margin-top: 0; }
.form-row { display: flex; gap: 10px; margin-bottom: 15px; align-items: center; flex-wrap: wrap; }
.form-row label { flex-basis: 150px; font-weight: bold; }
.form-row input, .form-row select, .form-row textarea { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px; }
.btn-calc { background-color: #f39c12; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin-left: 10px; font-size: 0.9em; }
.btn-calc:hover { background-color: #e67e22; }
</style>

<h2>予約の編集 (予約ID: <?php echo h($id); ?>)</h2>

<?php if (isset($error)): ?><p style="color: red;"><?php echo $error; ?></p><?php endif; ?>

<div class="edit-form">
    <form action="edit_booking.php?id=<?php echo h($id); ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

        <div class="form-row">
            <label for="guest_name">顧客名:</label>
            <input type="text" id="guest_name" name="guest_name" value="<?php echo h($booking['guest_name'] ?: ($booking['user_name'] ?? '')); ?>" required>
        </div>
        <div class="form-row">
            <label for="guest_email">メールアドレス:</label>
            <input type="email" id="guest_email" name="guest_email" value="<?php echo h($booking['guest_email'] ?: ($booking['user_email'] ?? '')); ?>">
        </div>
        <div class="form-row">
            <label for="guest_phone">電話番号:</label>
            <!-- guest_phoneがあればそれを、なければusers.phoneを表示 -->
            <input type="tel" id="guest_phone" name="guest_phone" value="<?php echo h($booking['guest_phone'] ?? ($booking['user_phone'] ?? '')); ?>">
        </div>

        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

        <div class="form-row">
            <label for="check_in_date">チェックイン日:</label>
            <input type="date" id="check_in_date" name="check_in_date" value="<?php echo h($booking['check_in_date']); ?>" required>
        </div>
        <div class="form-row">
            <label for="check_out_date">チェックアウト日:</label>
            <input type="date" id="check_out_date" name="check_out_date" value="<?php echo h($booking['check_out_date']); ?>" required>
        </div>
        <div class="form-row">
            <label for="num_guests">人数 (大人):</label>
            <input type="number" id="num_guests" name="num_guests" min="1" value="<?php echo h($booking['num_guests']); ?>" required>
            <small>単価: ¥<?php echo number_format(PRICE_PER_ADULT); ?></small>
        </div>
        <div class="form-row">
            <label for="num_children">人数 (子供):</label>
            <input type="number" id="num_children" name="num_children" min="0" value="<?php echo h($booking['num_children']); ?>">
            <small>単価: ¥<?php echo number_format(PRICE_PER_CHILD); ?></small>
        </div>

        <div class="form-row">
            <label for="total_price">合計金額 (円):</label>
            <input type="number" id="total_price" name="total_price" value="<?php echo h((int)$booking['total_price']); ?>" required>
            <button type="button" class="btn-calc" onclick="calculatePrice()">再計算する</button>
        </div>
        <p style="font-size: 0.9em; color: #666; margin-left: 160px; margin-top: -10px; margin-bottom: 20px;">
            ※人数や日程を変更した場合、「再計算する」ボタンを押すと金額が更新されます。
        </p>

        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

        <div class="form-row">
            <label for="check_in_time">到着予定:</label>
            <select id="check_in_time" name="check_in_time">
                <option value="">選択してください</option>
                <?php for($i = 15; $i <= 22; $i++): ?>
                    <?php $t = $i . ':00'; $sel = ($booking['check_in_time'] == $t) ? 'selected' : ''; ?>
                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                    <?php $t = $i . ':30'; $sel = ($booking['check_in_time'] == $t) ? 'selected' : ''; ?>
                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                <?php endfor; ?>
                <?php if ($booking['check_in_time'] && !preg_match('/^(1[5-9]|2[0-2]):(00|30)$/', $booking['check_in_time'])): ?>
                    <option value="<?php echo h($booking['check_in_time']); ?>" selected><?php echo h($booking['check_in_time']); ?></option>
                <?php endif; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="check_out_time">出発予定:</label>
            <select id="check_out_time" name="check_out_time">
                <option value="">選択してください</option>
                <?php for($i = 6; $i <= 11; $i++): ?>
                    <?php $t = $i . ':00'; $sel = ($booking['check_out_time'] == $t) ? 'selected' : ''; ?>
                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                    <?php $t = $i . ':30'; $sel = ($booking['check_out_time'] == $t) ? 'selected' : ''; ?>
                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                <?php endfor; ?>
                <?php if ($booking['check_out_time'] && !preg_match('/^([6-9]|10|11):(00|30)$/', $booking['check_out_time'])): ?>
                    <option value="<?php echo h($booking['check_out_time']); ?>" selected><?php echo h($booking['check_out_time']); ?></option>
                <?php endif; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="notes">備考:</label>
            <textarea id="notes" name="notes" rows="4"><?php echo h($booking['notes']); ?></textarea>
        </div>
        <div class="form-row">
            <label for="status">ステータス:</label>
            <select id="status" name="status">
                <option value="confirmed" <?php echo ($booking['status'] === 'confirmed') ? 'selected' : ''; ?>>確定</option>
                <option value="cancelled" <?php echo ($booking['status'] === 'cancelled') ? 'selected' : ''; ?>>キャンセル済</option>
            </select>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn-admin" style="background-color: #2980b9;">更新する</button>
            <a href="bookings.php" style="margin-left: 10px;">キャンセル</a>
        </div>
    </form>
</div>

<script>
    // PHPから定数を受け取る
    const ADULT_PRICE = <?php echo PRICE_PER_ADULT; ?>;
    const CHILD_PRICE = <?php echo PRICE_PER_CHILD; ?>;

    function calculatePrice() {
        const checkInInput = document.getElementById('check_in_date');
        const checkOutInput = document.getElementById('check_out_date');
        const numGuestsInput = document.getElementById('num_guests');
        const numChildrenInput = document.getElementById('num_children');
        const totalPriceInput = document.getElementById('total_price');

        const checkIn = new Date(checkInInput.value);
        const checkOut = new Date(checkOutInput.value);
        const adults = parseInt(numGuestsInput.value) || 0;
        const children = parseInt(numChildrenInput.value) || 0;

        if (checkIn && checkOut && checkOut > checkIn) {
            const diffTime = Math.abs(checkOut - checkIn);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            const total = diffDays * (adults * ADULT_PRICE + children * CHILD_PRICE);
            totalPriceInput.value = total;
            alert('合計金額を再計算しました: ¥' + total.toLocaleString());
        } else {
            alert('チェックイン日とチェックアウト日を正しく入力してください。');
        }
    }
</script>

<?php
require_once 'admin_footer.php';
?>
