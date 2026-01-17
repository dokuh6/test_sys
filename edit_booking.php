<?php
require_once 'includes/init.php';

// Initialize variables
$errors = [];
$message = '';
$booking = null;
$can_edit = false;
$nights = 0;

// URL parameters
$booking_id = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);
$token = filter_input(INPUT_GET, 'token');
$booking_number = filter_input(INPUT_GET, 'booking_number');
$email = filter_input(INPUT_GET, 'guest_email');

// If submitting form, these might be in POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $token = filter_input(INPUT_POST, 'token');
    $booking_number = filter_input(INPUT_POST, 'booking_number');
    $email = filter_input(INPUT_POST, 'guest_email');
}

// Access Control Logic (Shared with confirm.php mostly)
if (!$booking_id && !$token && !$booking_number) {
    header('Location: index.php');
    exit();
}

$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

// Determine identification method
if ($token) {
    $search_by_token = true;
} elseif ($booking_number && $email) {
    $search_by_token = false;
    $search_by_number_email = true;
} else {
    $search_by_token = false;
    // Check session permissions for ID-only access
    if (isset($_SESSION['last_booking_id']) && $_SESSION['last_booking_id'] == $booking_id) {
        $can_edit = true;
    } elseif (isset($user_id)) {
        // Will verify owner after fetch
    } else {
        $errors[] = t('error_permission_denied') ?? "このページを表示する権限がありません。";
    }
}

// Fetch Booking
if (empty($errors)) {
    try {
        $sql = "SELECT
                    b.*,
                    r.name as room_name,
                    r.name_en as room_name_en,
                    r.price as room_price,
                    br.room_id
                FROM bookings b
                JOIN booking_rooms br ON b.id = br.booking_id
                JOIN rooms r ON br.room_id = r.id";

        if ($search_by_token) {
            $sql .= " WHERE b.booking_token = :token";
        } elseif (isset($search_by_number_email) && $search_by_number_email) {
            $sql .= " WHERE b.booking_number = :booking_number AND b.guest_email = :email";
        } else {
            if ($booking_number) {
                 $sql .= " WHERE b.booking_number = :booking_number";
            } else {
                 $sql .= " WHERE b.id = :booking_id";
            }
        }

        $stmt = $dbh->prepare($sql);

        if ($search_by_token) {
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        } elseif (isset($search_by_number_email) && $search_by_number_email) {
            $stmt->bindParam(':booking_number', $booking_number, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        } else {
            if ($booking_number) {
                $stmt->bindParam(':booking_number', $booking_number, PDO::PARAM_STR);
            } else {
                $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            // Verify ownership if not already verified by token/email
            if ($search_by_token || (isset($search_by_number_email) && $search_by_number_email)) {
                 // セキュリティ: チェックアウト日から一定期間過ぎたら編集不可
                 $expiry_date = date('Y-m-d', strtotime($booking['check_out_date'] . ' + 30 days'));
                 if (date('Y-m-d') > $expiry_date) {
                     $errors[] = t('error_token_expired') ?? "リンクの有効期限が切れています。";
                     $can_edit = false;
                 } else {
                     $can_edit = true;
                 }
            } elseif (isset($user_id) && $booking['user_id'] == $user_id) {
                $can_edit = true;
            }
        }

        if (!$can_edit && empty($errors)) {
             $errors[] = t('error_permission_denied') ?? "このページを表示する権限がありません。";
        }

    } catch (PDOException $e) {
        $errors[] = "データベースエラー: " . h($e->getMessage());
    }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit && empty($errors)) {
    validate_csrf_token();

    $new_guest_name = filter_input(INPUT_POST, 'guest_name');
    $new_guest_phone = filter_input(INPUT_POST, 'guest_phone');
    $new_notes = filter_input(INPUT_POST, 'notes');
    $new_check_in_time = filter_input(INPUT_POST, 'check_in_time');
    $new_check_out_time = filter_input(INPUT_POST, 'check_out_time');

    // Advanced fields
    $new_check_in_date = filter_input(INPUT_POST, 'check_in_date');
    $new_check_out_date = filter_input(INPUT_POST, 'check_out_date');
    $new_num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $new_num_children = filter_input(INPUT_POST, 'num_children', FILTER_VALIDATE_INT) ?? 0;

    if ($new_guest_name && $new_check_in_date && $new_check_out_date && $new_num_guests) {
        if (strtotime($new_check_in_date) >= strtotime($new_check_out_date)) {
            $errors[] = "チェックアウト日はチェックイン日より後の日付にしてください。";
        } else {
            // Availability Check
            try {
                $sql_check = "SELECT b.id FROM bookings b
                              JOIN booking_rooms br ON b.id = br.booking_id
                              WHERE br.room_id = :room_id
                              AND b.status = 'confirmed'
                              AND b.id != :current_id
                              AND (b.check_in_date < :check_out_date AND b.check_out_date > :check_in_date)";
                $stmt_check = $dbh->prepare($sql_check);
                $stmt_check->execute([
                    ':room_id' => $booking['room_id'],
                    ':current_id' => $booking['id'],
                    ':check_in_date' => $new_check_in_date,
                    ':check_out_date' => $new_check_out_date
                ]);

                if ($stmt_check->fetch()) {
                    $errors[] = "指定された期間は既に予約が入っています。";
                } else {
                    // Recalculate Price
                    $diffTime = abs(strtotime($new_check_out_date) - strtotime($new_check_in_date));
                    $diffDays = ceil($diffTime / (60 * 60 * 24));
                    $new_total_price = $diffDays * ($new_num_guests * PRICE_PER_ADULT + $new_num_children * PRICE_PER_CHILD);

                    // Update DB
                    $sql_update = "UPDATE bookings SET
                        guest_name = :guest_name,
                        guest_phone = :guest_phone,
                        check_in_date = :check_in_date,
                        check_out_date = :check_out_date,
                        num_guests = :num_guests,
                        num_children = :num_children,
                        check_in_time = :check_in_time,
                        check_out_time = :check_out_time,
                        notes = :notes,
                        total_price = :total_price,
                        updated_at = NOW()
                        WHERE id = :id";

                    $stmt_update = $dbh->prepare($sql_update);
                    $stmt_update->execute([
                        ':guest_name' => $new_guest_name,
                        ':guest_phone' => $new_guest_phone,
                        ':check_in_date' => $new_check_in_date,
                        ':check_out_date' => $new_check_out_date,
                        ':num_guests' => $new_num_guests,
                        ':num_children' => $new_num_children,
                        ':check_in_time' => $new_check_in_time,
                        ':check_out_time' => $new_check_out_time,
                        ':notes' => $new_notes,
                        ':total_price' => $new_total_price,
                        ':id' => $booking['id']
                    ]);

                    if (isset($_SESSION['user']['id'])) {
                        log_admin_action($dbh, $_SESSION['user']['id'], 'update_booking_user', ['booking_id' => $booking['id']]);
                    }

                    // メール送信
                    send_booking_modification_email($booking['id'], $dbh);

                    // Redirect back to confirm with success
                    // Build query string based on access method
                    $params = [];
                    if ($token) {
                        $params[] = "token=" . urlencode($token);
                    } elseif ($booking_number && $email) {
                        $params[] = "booking_number=" . urlencode($booking_number);
                        $params[] = "guest_email=" . urlencode($email);
                    } else {
                        $params[] = "booking_id=" . urlencode($booking['id']);
                    }

                    header('Location: confirm.php?' . implode('&', $params));
                    exit();
                }
            } catch (Exception $e) {
                $errors[] = "更新エラー: " . h($e->getMessage());
            }
        }
    } else {
        $errors[] = "必須項目を入力してください。";
    }
}

require_once 'includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="max-w-4xl mx-auto my-12 px-4">
    <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-8">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">予約内容の変更</h2>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded mb-6">
                <?php foreach ($errors as $e): ?>
                    <p><?php echo h($e); ?></p>
                <?php endforeach; ?>
            </div>
            <?php if (!$can_edit): ?>
                <a href="index.php" class="text-primary hover:underline">トップへ戻る</a>
                </div></div>
                <?php require_once 'includes/footer.php'; exit(); ?>
            <?php endif; ?>
        <?php endif; ?>

        <form action="edit_booking.php" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="booking_id" value="<?php echo h($booking['id']); ?>">
            <?php if ($token): ?><input type="hidden" name="token" value="<?php echo h($token); ?>"><?php endif; ?>
            <?php if ($booking_number): ?><input type="hidden" name="booking_number" value="<?php echo h($booking_number); ?>"><?php endif; ?>
            <?php if ($email): ?><input type="hidden" name="guest_email" value="<?php echo h($email); ?>"><?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Info -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">お名前</label>
                    <input type="text" name="guest_name" value="<?php echo h($booking['guest_name']); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">電話番号</label>
                    <input type="tel" name="guest_phone" value="<?php echo h($booking['guest_phone'] ?? ''); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border">
                </div>

                <!-- Dates & Guests (Important) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">チェックイン日</label>
                    <input type="date" name="check_in_date" id="check_in_date" value="<?php echo h($booking['check_in_date']); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">チェックアウト日</label>
                    <input type="date" name="check_out_date" id="check_out_date" value="<?php echo h($booking['check_out_date']); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">大人人数</label>
                    <input type="number" name="num_guests" id="num_guests" value="<?php echo h($booking['num_guests']); ?>" min="1" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">子供人数</label>
                    <input type="number" name="num_children" id="num_children" value="<?php echo h($booking['num_children']); ?>" min="0" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border">
                </div>

                <!-- Times -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">到着予定時刻</label>
                    <select name="check_in_time" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border">
                        <option value="">選択してください</option>
                        <?php for($i = 15; $i <= 22; $i++): ?>
                            <?php $t = $i . ':00'; $sel = ($booking['check_in_time'] == $t) ? 'selected' : ''; ?>
                            <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                            <?php $t = $i . ':30'; $sel = ($booking['check_in_time'] == $t) ? 'selected' : ''; ?>
                            <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                 <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">出発予定時刻</label>
                    <select name="check_out_time" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border">
                        <option value="">選択してください</option>
                        <?php for($i = 6; $i <= 11; $i++): ?>
                            <?php $t = $i . ':00'; $sel = ($booking['check_out_time'] == $t) ? 'selected' : ''; ?>
                            <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                            <?php $t = $i . ':30'; $sel = ($booking['check_out_time'] == $t) ? 'selected' : ''; ?>
                            <option value="<?php echo $t; ?>" <?php echo $sel; ?>><?php echo $t; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">備考</label>
                <textarea name="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2 border"><?php echo h($booking['notes']); ?></textarea>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-lg flex justify-between items-center">
                <span class="font-bold text-gray-700 dark:text-gray-200">概算合計金額:</span>
                <span class="text-xl font-bold text-primary" id="display_price">¥<?php echo number_format($booking['total_price']); ?></span>
            </div>

            <div class="flex justify-between items-center pt-4">
                <a href="javascript:history.back()" class="text-gray-600 hover:underline">戻る</a>
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded shadow transition-colors">
                    変更を保存する
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const ADULT_PRICE = <?php echo PRICE_PER_ADULT; ?>;
    const CHILD_PRICE = <?php echo PRICE_PER_CHILD; ?>;

    function calculatePrice() {
        const checkInInput = document.getElementById('check_in_date');
        const checkOutInput = document.getElementById('check_out_date');
        const numGuestsInput = document.getElementById('num_guests');
        const numChildrenInput = document.getElementById('num_children');
        const priceDisplay = document.getElementById('display_price');

        const checkIn = new Date(checkInInput.value);
        const checkOut = new Date(checkOutInput.value);
        const adults = parseInt(numGuestsInput.value) || 0;
        const children = parseInt(numChildrenInput.value) || 0;

        if (checkIn && checkOut && checkOut > checkIn) {
            const diffTime = Math.abs(checkOut - checkIn);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            const total = diffDays * (adults * ADULT_PRICE + children * CHILD_PRICE);
            priceDisplay.textContent = '¥' + total.toLocaleString();
        }
    }

    // Attach listeners
    ['check_in_date', 'check_out_date', 'num_guests', 'num_children'].forEach(id => {
        document.getElementById(id).addEventListener('change', calculatePrice);
        document.getElementById(id).addEventListener('input', calculatePrice);
    });
</script>

<?php require_once 'includes/footer.php'; ?>
