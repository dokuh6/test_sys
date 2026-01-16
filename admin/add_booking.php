<?php
require_once 'admin_check.php';
require_once '../includes/functions.php';
require_once '../includes/config.php';

$message = '';
$error = '';
$room_id = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
$check_in = filter_input(INPUT_GET, 'check_in');
$check_out = filter_input(INPUT_GET, 'check_out');

// デフォルト値
if (!$check_in) {
    $check_in = date('Y-m-d');
}
if (!$check_out) {
    $check_out = date('Y-m-d', strtotime('+1 day'));
}

// 部屋一覧取得
try {
    $sql_rooms = "SELECT id, name, price FROM rooms ORDER BY id";
    $stmt_rooms = $dbh->query($sql_rooms);
    $rooms = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "部屋情報の取得に失敗しました。";
    $rooms = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $check_in = filter_input(INPUT_POST, 'check_in');
    $check_out = filter_input(INPUT_POST, 'check_out');
    $guest_name = filter_input(INPUT_POST, 'guest_name');
    $guest_email = filter_input(INPUT_POST, 'guest_email', FILTER_VALIDATE_EMAIL);
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $num_children = filter_input(INPUT_POST, 'num_children', FILTER_VALIDATE_INT) ?? 0;
    $check_in_time = filter_input(INPUT_POST, 'check_in_time');
    $check_out_time = filter_input(INPUT_POST, 'check_out_time');
    $notes = filter_input(INPUT_POST, 'notes');
    $total_price = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_INT); // 管理者が手入力または計算結果を送信

    // 入力チェック
    if (!$room_id || !$check_in || !$check_out || !$guest_name || !$num_guests || !$total_price) {
        $error = t('admin_input_error');
    } elseif (strtotime($check_in) >= strtotime($check_out)) {
        $error = t('error_checkout_after_checkin');
    } else {
        try {
            $dbh->beginTransaction();

            // 部屋のロックと空き確認
            $sql_check = "SELECT b.id FROM bookings b
                          JOIN booking_rooms br ON b.id = br.booking_id
                          WHERE br.room_id = :room_id
                          AND b.status = 'confirmed'
                          AND (b.check_in_date < :check_out_date AND b.check_out_date > :check_in_date)
                          FOR UPDATE"; // 簡易的なロック（厳密にはroomsテーブルロックが望ましいがここでは行ロック）

            $stmt_check = $dbh->prepare($sql_check);
            $stmt_check->execute([
                ':room_id' => $room_id,
                ':check_in_date' => $check_in,
                ':check_out_date' => $check_out
            ]);

            if ($stmt_check->fetch()) {
                throw new Exception(t('book_error_taken'));
            }

            // 予約作成
            // 管理者作成の予約は user_id = NULL とする（ゲスト予約扱い）
            // 将来的には既存ユーザーを選択するUIを追加することも可能
            $booking_token = bin2hex(random_bytes(32));

            $sql = "INSERT INTO bookings (booking_token, user_id, guest_name, guest_email, check_in_date, check_out_date, check_in_time, check_out_time, num_guests, num_children, notes, total_price, status)
                    VALUES (:booking_token, NULL, :guest_name, :guest_email, :check_in_date, :check_out_date, :check_in_time, :check_out_time, :num_guests, :num_children, :notes, :total_price, 'confirmed')";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                ':booking_token' => $booking_token,
                ':guest_name' => $guest_name,
                ':guest_email' => $guest_email ? $guest_email : '', // email任意の場合は空文字
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

            // 部屋紐付け
            $sql_br = "INSERT INTO booking_rooms (booking_id, room_id) VALUES (:booking_id, :room_id)";
            $stmt_br = $dbh->prepare($sql_br);
            $stmt_br->execute([':booking_id' => $booking_id, ':room_id' => $room_id]);

            $dbh->commit();

            // 完了後にカレンダーへリダイレクト
            $_SESSION['message'] = t('admin_add_success');
            header('Location: calendar.php');
            exit();

        } catch (Exception $e) {
            $dbh->rollBack();
            $error = t('admin_add_fail') . ": " . h($e->getMessage());
        }
    }
}

$csrf_token = generate_csrf_token();
require_once 'admin_header.php';
?>

<h2><?php echo h(t('admin_add_booking')); ?></h2>

<?php if ($error): ?>
    <p style="color: red;"><?php echo $error; ?></p>
<?php endif; ?>

<form action="add_booking.php" method="post" style="max-width: 600px;">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

    <div style="margin-bottom: 1rem;">
        <label><?php echo h(t('booking_info_room')); ?>:</label>
        <select name="room_id" id="room_select" required style="width: 100%; padding: 8px;">
            <option value=""><?php echo h(t('book_select_placeholder')); ?></option>
            <?php foreach ($rooms as $room): ?>
                <option value="<?php echo h($room['id']); ?>" data-price="<?php echo h($room['price']); ?>" <?php echo $room_id == $room['id'] ? 'selected' : ''; ?>>
                    <?php echo h($room['name']); ?> (¥<?php echo number_format($room['price']); ?>/<?php echo h(t('room_price_per_night', '')); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
        <div style="flex: 1;">
            <label><?php echo h(t('booking_info_check_in')); ?>:</label>
            <input type="date" name="check_in" id="check_in" value="<?php echo h($check_in); ?>" required style="width: 100%; padding: 8px;">
        </div>
        <div style="flex: 1;">
            <label><?php echo h(t('booking_info_check_out')); ?>:</label>
            <input type="date" name="check_out" id="check_out" value="<?php echo h($check_out); ?>" required style="width: 100%; padding: 8px;">
        </div>
    </div>

    <div style="margin-bottom: 1rem;">
        <label><?php echo h(t('form_name')); ?>:</label>
        <input type="text" name="guest_name" required style="width: 100%; padding: 8px;">
    </div>

    <div style="margin-bottom: 1rem;">
        <label><?php echo h(t('form_email')); ?> (Optional):</label>
        <input type="email" name="guest_email" style="width: 100%; padding: 8px;">
    </div>

    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
        <div style="flex: 1;">
            <label><?php echo h(t('form_num_guests')); ?> (<?php echo number_format(PRICE_PER_ADULT); ?>円):</label>
            <input type="number" name="num_guests" id="num_guests" value="1" min="1" required style="width: 100%; padding: 8px;">
        </div>
        <div style="flex: 1;">
            <label><?php echo h(t('form_children')); ?> (<?php echo number_format(PRICE_PER_CHILD); ?>円):</label>
            <input type="number" name="num_children" id="num_children" value="0" min="0" style="width: 100%; padding: 8px;">
        </div>
    </div>

    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
        <div style="flex: 1;">
            <label><?php echo h(t('book_check_in_time_label')); ?>:</label>
            <select name="check_in_time" style="width: 100%; padding: 8px;">
                <option value=""><?php echo h(t('book_select_placeholder')); ?></option>
                <?php for($i = 15; $i <= 22; $i++): ?>
                    <option value="<?php echo $i; ?>:00"><?php echo $i; ?>:00</option>
                    <option value="<?php echo $i; ?>:30"><?php echo $i; ?>:30</option>
                <?php endfor; ?>
            </select>
        </div>
        <div style="flex: 1;">
            <label><?php echo h(t('book_check_out_time_label')); ?>:</label>
            <select name="check_out_time" style="width: 100%; padding: 8px;">
                <option value=""><?php echo h(t('book_select_placeholder')); ?></option>
                <?php for($i = 6; $i <= 11; $i++): ?>
                    <option value="<?php echo $i; ?>:00"><?php echo $i; ?>:00</option>
                    <option value="<?php echo $i; ?>:30"><?php echo $i; ?>:30</option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <div style="margin-bottom: 1rem;">
        <label><?php echo h(t('book_notes_label')); ?>:</label>
        <textarea name="notes" rows="3" style="width: 100%; padding: 8px;"></textarea>
    </div>

    <div style="margin-bottom: 1rem;">
        <label><?php echo h(t('admin_total_price')); ?>:</label>
        <input type="number" name="total_price" id="total_price" required style="width: 100%; padding: 8px;">
        <small>※自動計算されますが、手動で変更可能です</small>
    </div>

    <button type="submit" class="btn-admin" style="background-color: #27ae60; padding: 10px 20px; font-size: 1rem;"><?php echo h(t('admin_add')); ?></button>
    <a href="calendar.php" class="btn-admin" style="background-color: #95a5a6; margin-left: 10px;"><?php echo h(t('admin_cancel')); ?></a>
</form>

<script>
    // JS側でも定数を使いたいが、PHPから渡すのが手っ取り早い
    const ADULT_PRICE = <?php echo PRICE_PER_ADULT; ?>;
    const CHILD_PRICE = <?php echo PRICE_PER_CHILD; ?>;

    function calculatePrice() {
        const roomSelect = document.getElementById('room_select');
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        const numGuestsInput = document.getElementById('num_guests');
        const numChildrenInput = document.getElementById('num_children');
        const totalPriceInput = document.getElementById('total_price');

        const selectedOption = roomSelect.options[roomSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        // const pricePerNight = parseInt(selectedOption.dataset.price); // Deprecated
        const checkIn = new Date(checkInInput.value);
        const checkOut = new Date(checkOutInput.value);
        const adults = parseInt(numGuestsInput.value) || 0;
        const children = parseInt(numChildrenInput.value) || 0;

        if (checkIn && checkOut && checkOut > checkIn) {
            const diffTime = Math.abs(checkOut - checkIn);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            // 新しい計算ロジック
            const total = diffDays * (adults * ADULT_PRICE + children * CHILD_PRICE);
            totalPriceInput.value = total;
        }
    }

    // 空室チェック関数
    function checkAvailability() {
        const roomSelect = document.getElementById('room_select');
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        const roomId = roomSelect.value;
        const checkIn = checkInInput.value;
        const checkOut = checkOutInput.value;

        if (!roomId || !checkIn || !checkOut) return;

        // API呼び出し
        fetch(`api/check_availability.php?room_id=${roomId}&check_in=${checkIn}&check_out=${checkOut}`)
            .then(response => response.json())
            .then(data => {
                const warningDiv = document.getElementById('availability_warning');
                if (!data.available) {
                    if (!warningDiv) {
                        const div = document.createElement('div');
                        div.id = 'availability_warning';
                        div.style.color = 'red';
                        div.style.fontWeight = 'bold';
                        div.style.marginBottom = '10px';
                        div.innerText = '警告: ' + data.message;
                        document.querySelector('form').insertBefore(div, document.querySelector('form').firstChild);
                    } else {
                        warningDiv.innerText = '警告: ' + data.message;
                    }
                } else {
                    if (warningDiv) {
                        warningDiv.remove();
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }

    document.getElementById('room_select').addEventListener('change', () => { calculatePrice(); checkAvailability(); });
    document.getElementById('check_in').addEventListener('change', () => { calculatePrice(); checkAvailability(); });
    document.getElementById('check_out').addEventListener('change', () => { calculatePrice(); checkAvailability(); });
    document.getElementById('num_guests').addEventListener('change', calculatePrice);
    document.getElementById('num_guests').addEventListener('input', calculatePrice);
    document.getElementById('num_children').addEventListener('change', calculatePrice);
    document.getElementById('num_children').addEventListener('input', calculatePrice);

    // 初期表示時に計算
    calculatePrice();
    checkAvailability();
</script>

<?php
require_once 'admin_footer.php';
?>
