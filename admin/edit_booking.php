<?php
require_once 'admin_check.php';

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
    $check_in_date = filter_input(INPUT_POST, 'check_in_date');
    $check_out_date = filter_input(INPUT_POST, 'check_out_date');
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $num_children = filter_input(INPUT_POST, 'num_children', FILTER_VALIDATE_INT) ?? 0;
    $check_in_time = filter_input(INPUT_POST, 'check_in_time');
    $check_out_time = filter_input(INPUT_POST, 'check_out_time');
    $notes = filter_input(INPUT_POST, 'notes');
    $status = filter_input(INPUT_POST, 'status');
    // TODO: 料金の再計算ロジック (管理画面編集時は一旦再計算しない、必要なら手動修正フィールド追加など)
    // $total_price = ...

    if ($guest_name && $guest_email && $check_in_date && $check_out_date && $num_guests && $status) {
        try {
            $sql = "UPDATE bookings SET guest_name = :guest_name, guest_email = :guest_email, check_in_date = :check_in_date, check_out_date = :check_out_date, num_guests = :num_guests, num_children = :num_children, check_in_time = :check_in_time, check_out_time = :check_out_time, notes = :notes, status = :status WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                ':guest_name' => $guest_name,
                ':guest_email' => $guest_email,
                ':check_in_date' => $check_in_date,
                ':check_out_date' => $check_out_date,
                ':num_guests' => $num_guests,
                ':num_children' => $num_children,
                ':check_in_time' => $check_in_time,
                ':check_out_time' => $check_out_time,
                ':notes' => $notes,
                ':status' => $status,
                ':id' => $id
            ]);
            $_SESSION['message'] = "予約ID: " . h($id) . " の情報を更新しました。";
            header('Location: bookings.php');
            exit();
        } catch (PDOException $e) {
            $error = "更新に失敗しました: " . h($e->getMessage());
        }
    } else {
        $error = "すべての項目を正しく入力してください。";
    }
}

// --- データ表示 (GET) ---
try {
    // 編集対象の予約情報を取得
    $stmt = $dbh->prepare("SELECT * FROM bookings WHERE id = :id");
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
.edit-form { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px; }
.edit-form h3 { margin-top: 0; }
.form-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
.form-row label { flex-basis: 120px; }
.form-row input, .form-row select { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
</style>

<h2>予約の編集 (予約ID: <?php echo h($id); ?>)</h2>

<?php if (isset($error)): ?><p style="color: red;"><?php echo $error; ?></p><?php endif; ?>

<div class="edit-form">
    <form action="edit_booking.php?id=<?php echo h($id); ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

        <div class="form-row">
            <label for="guest_name">顧客名:</label>
            <input type="text" id="guest_name" name="guest_name" value="<?php echo h($booking['guest_name'] ?: ($booking['user_id'] ? '登録ユーザー' : '')); ?>" required>
        </div>
        <div class="form-row">
            <label for="guest_email">メールアドレス:</label>
            <input type="email" id="guest_email" name="guest_email" value="<?php echo h($booking['guest_email']); ?>" required>
        </div>
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
        </div>
        <div class="form-row">
            <label for="num_children">人数 (子供):</label>
            <input type="number" id="num_children" name="num_children" min="0" value="<?php echo h($booking['num_children']); ?>">
        </div>
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
                <!-- Custom value fallback -->
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
            <textarea id="notes" name="notes" rows="4" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><?php echo h($booking['notes']); ?></textarea>
        </div>
        <div class="form-row">
            <label for="status">ステータス:</label>
            <select id="status" name="status">
                <option value="confirmed" <?php echo ($booking['status'] === 'confirmed') ? 'selected' : ''; ?>>確定</option>
                <option value="cancelled" <?php echo ($booking['status'] === 'cancelled') ? 'selected' : ''; ?>>キャンセル済</option>
            </select>
        </div>

        <button type="submit" class="btn-admin" style="background-color: #2980b9;">更新する</button>
        <a href="bookings.php" style="margin-left: 10px;">キャンセル</a>
    </form>
</div>

<?php
require_once 'admin_footer.php';
?>
