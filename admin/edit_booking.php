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
    $guest_phone = filter_input(INPUT_POST, 'guest_phone'); // Phone number input
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
            $error = t('error_checkout_after_checkin');
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
                        throw new Exception(t('book_error_taken'));
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
                    ':guest_phone' => $guest_phone ? $guest_phone : '', // Bind phone
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

                $_SESSION['message'] = t('admin_update_success');
                header('Location: bookings.php');
                exit();
            } catch (PDOException $e) {
                // guest_phoneカラムがない場合のエラーハンドリングなどを考慮
                $error = t('admin_update_fail', h($e->getMessage()));
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        $error = t('admin_input_error');
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
    die("Error: " . h($e->getMessage()));
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

<h2><?php echo h(t('admin_bookings_title')); ?> (ID: <?php echo h($id); ?>)</h2>

<?php if (isset($error)): ?><p style="color: red;"><?php echo h($error); ?></p><?php endif; ?>

<div class="edit-form">
    <form action="edit_booking.php?id=<?php echo h($id); ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

        <div class="form-row">
            <label for="guest_name"><?php echo h(t('form_name')); ?>:</label>
            <input type="text" id="guest_name" name="guest_name" value="<?php echo h($booking['guest_name'] ?: ($booking['user_name'] ?? '')); ?>" required>
        </div>
        <div class="form-row">
            <label for="guest_email"><?php echo h(t('form_email')); ?>:</label>
            <input type="email" id="guest_email" name="guest_email" value="<?php echo h($booking['guest_email'] ?: ($booking['user_email'] ?? '')); ?>">
        </div>
        <div class="form-row">
            <label for="guest_phone"><?php echo h(t('form_tel')); ?>:</label>
            <!-- guest_phoneがあればそれを、なければusers.phoneを表示 -->
            <input type="tel" id="guest_phone" name="guest_phone" value="<?php echo h($booking['guest_phone'] ?? ($booking['user_phone'] ?? '')); ?>">
        </div>

        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

        <div class="form-row">
            <label for="check_in_date"><?php echo h(t('booking_info_check_in')); ?>:</label>
            <input type="date" id="check_in_date" name="check_in_date" value="<?php echo h($booking['check_in_date']); ?>" required>
        </div>
        <div class="form-row">
            <label for="check_out_date"><?php echo h(t('booking_info_check_out')); ?>:</label>
            <input type="date" id="check_out_date" name="check_out_date" value="<?php echo h($booking['check_out_date']); ?>" required>
        </div>
        <div class="form-row">
            <label for="num_guests"><?php echo h(t('form_num_guests')); ?> (<?php echo h(t('pricing_adult')); ?>):</label>
            <input type="number" id="num_guests" name="num_guests" min="1" value="<?php echo h($booking['num_guests']); ?>" required>
            <small><?php echo h(t('admin_unit_price')); ?>: ¥<?php echo number_format(PRICE_PER_ADULT); ?></small>
        </div>
        <div class="form-row">
            <label for="num_children"><?php echo h(t('form_children')); ?> (<?php echo h(t('pricing_child')); ?>):</label>
            <input type="number" id="num_children" name="num_children" min="0" value="<?php echo h($booking['num_children']); ?>">
            <small><?php echo h(t('admin_unit_price')); ?>: ¥<?php echo number_format(PRICE_PER_CHILD); ?></small>
        </div>

        <div class="form-row">
            <label for="total_price"><?php echo h(t('admin_total_price')); ?>:</label>
            <input type="number" id="total_price" name="total_price" value="<?php echo h((int)$booking['total_price']); ?>" required>
            <button type="button" class="btn-calc" onclick="calculatePrice()">Recalculate</button>
        </div>
        <p style="font-size: 0.9em; color: #666; margin-left: 160px; margin-top: -10px; margin-bottom: 20px;">
            <?php echo h(t('admin_hint_recalc')); ?>
        </p>

        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

        <div class="form-row">
            <label for="check_in_time"><?php echo h(t('book_check_in_time_label')); ?>:</label>
            <select id="check_in_time" name="check_in_time">
                <option value=""><?php echo h(t('book_select_placeholder')); ?></option>
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
            <label for="check_out_time"><?php echo h(t('book_check_out_time_label')); ?>:</label>
            <select id="check_out_time" name="check_out_time">
                <option value=""><?php echo h(t('book_select_placeholder')); ?></option>
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
            <label for="notes"><?php echo h(t('book_notes_label')); ?>:</label>
            <textarea id="notes" name="notes" rows="4"><?php echo h($booking['notes']); ?></textarea>
        </div>
        <div class="form-row">
            <label for="status"><?php echo h(t('history_status')); ?>:</label>
            <select id="status" name="status">
                <option value="confirmed" <?php echo ($booking['status'] === 'confirmed') ? 'selected' : ''; ?>><?php echo h(t('status_confirmed')); ?></option>
                <option value="cancelled" <?php echo ($booking['status'] === 'cancelled') ? 'selected' : ''; ?>><?php echo h(t('status_cancelled')); ?></option>
            </select>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn-admin" style="background-color: #2980b9;"><?php echo h(t('admin_update')); ?></button>
            <a href="bookings.php" style="margin-left: 10px;"><?php echo h(t('admin_cancel')); ?></a>
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
            alert('<?php echo h(t('admin_recalc_msg')); ?>' + total.toLocaleString());
        } else {
            alert('<?php echo h(t('book_error_dates')); ?>');
        }
    }
</script>

<?php
require_once 'admin_footer.php';
?>
