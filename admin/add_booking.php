<?php
require_once 'admin_check.php';
require_once '../includes/functions.php';

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
    $total_price = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_INT); // 管理者が手入力または計算結果を送信

    // 入力チェック
    if (!$room_id || !$check_in || !$check_out || !$guest_name || !$num_guests || !$total_price) {
        $error = "必須項目が入力されていません。";
    } elseif (strtotime($check_in) >= strtotime($check_out)) {
        $error = "チェックアウト日はチェックイン日より後の日付にしてください。";
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
                throw new Exception("指定された期間は既に予約が入っています。");
            }

            // 予約作成
            // 管理者作成の予約は user_id = NULL とする（ゲスト予約扱い）
            // 将来的には既存ユーザーを選択するUIを追加することも可能
            $sql = "INSERT INTO bookings (user_id, guest_name, guest_email, check_in_date, check_out_date, num_guests, total_price, status)
                    VALUES (NULL, :guest_name, :guest_email, :check_in_date, :check_out_date, :num_guests, :total_price, 'confirmed')";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                ':guest_name' => $guest_name,
                ':guest_email' => $guest_email ? $guest_email : '', // email任意の場合は空文字
                ':check_in_date' => $check_in,
                ':check_out_date' => $check_out,
                ':num_guests' => $num_guests,
                ':total_price' => $total_price
            ]);
            $booking_id = $dbh->lastInsertId();

            // 部屋紐付け
            $sql_br = "INSERT INTO booking_rooms (booking_id, room_id) VALUES (:booking_id, :room_id)";
            $stmt_br = $dbh->prepare($sql_br);
            $stmt_br->execute([':booking_id' => $booking_id, ':room_id' => $room_id]);

            $dbh->commit();

            // 完了後にカレンダーへリダイレクト
            $_SESSION['message'] = "予約を作成しました (ID: $booking_id)";
            header('Location: calendar.php');
            exit();

        } catch (Exception $e) {
            $dbh->rollBack();
            $error = "予約作成エラー: " . h($e->getMessage());
        }
    }
}

$csrf_token = generate_csrf_token();
require_once 'admin_header.php';
?>

<h2>新規予約作成</h2>

<?php if ($error): ?>
    <p style="color: red;"><?php echo $error; ?></p>
<?php endif; ?>

<form action="add_booking.php" method="post" style="max-width: 600px;">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

    <div style="margin-bottom: 1rem;">
        <label>部屋:</label>
        <select name="room_id" id="room_select" required style="width: 100%; padding: 8px;">
            <option value="">選択してください</option>
            <?php foreach ($rooms as $room): ?>
                <option value="<?php echo h($room['id']); ?>" data-price="<?php echo h($room['price']); ?>" <?php echo $room_id == $room['id'] ? 'selected' : ''; ?>>
                    <?php echo h($room['name']); ?> (¥<?php echo number_format($room['price']); ?>/泊)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
        <div style="flex: 1;">
            <label>チェックイン:</label>
            <input type="date" name="check_in" id="check_in" value="<?php echo h($check_in); ?>" required style="width: 100%; padding: 8px;">
        </div>
        <div style="flex: 1;">
            <label>チェックアウト:</label>
            <input type="date" name="check_out" id="check_out" value="<?php echo h($check_out); ?>" required style="width: 100%; padding: 8px;">
        </div>
    </div>

    <div style="margin-bottom: 1rem;">
        <label>ゲスト氏名:</label>
        <input type="text" name="guest_name" required style="width: 100%; padding: 8px;">
    </div>

    <div style="margin-bottom: 1rem;">
        <label>ゲストEmail (任意):</label>
        <input type="email" name="guest_email" style="width: 100%; padding: 8px;">
    </div>

    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
        <div style="flex: 1;">
            <label>宿泊人数:</label>
            <input type="number" name="num_guests" value="1" min="1" required style="width: 100%; padding: 8px;">
        </div>
        <div style="flex: 1;">
            <label>合計金額 (円):</label>
            <input type="number" name="total_price" id="total_price" required style="width: 100%; padding: 8px;">
            <small>※自動計算されますが、手動で変更可能です</small>
        </div>
    </div>

    <button type="submit" class="btn-admin" style="background-color: #27ae60; padding: 10px 20px; font-size: 1rem;">予約を作成する</button>
    <a href="calendar.php" class="btn-admin" style="background-color: #95a5a6; margin-left: 10px;">キャンセル</a>
</form>

<script>
    function calculatePrice() {
        const roomSelect = document.getElementById('room_select');
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        const totalPriceInput = document.getElementById('total_price');

        const selectedOption = roomSelect.options[roomSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        const pricePerNight = parseInt(selectedOption.dataset.price);
        const checkIn = new Date(checkInInput.value);
        const checkOut = new Date(checkOutInput.value);

        if (checkIn && checkOut && checkOut > checkIn) {
            const diffTime = Math.abs(checkOut - checkIn);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            totalPriceInput.value = pricePerNight * diffDays;
        }
    }

    document.getElementById('room_select').addEventListener('change', calculatePrice);
    document.getElementById('check_in').addEventListener('change', calculatePrice);
    document.getElementById('check_out').addEventListener('change', calculatePrice);

    // 初期表示時に計算
    calculatePrice();
</script>

<?php
require_once 'admin_footer.php';
?>
