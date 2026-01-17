<?php
require_once 'admin_check.php';

// Check if Manager or Staff
// Staff needs to manage customers.
require_permission([ROLE_MANAGER, ROLE_STAFF]);

$message = '';
$error = '';
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    header('Location: users.php');
    exit();
}

// ターゲットユーザーの情報を先に取得（権限チェックのため）
try {
    $stmt_target = $dbh->prepare("SELECT * FROM users WHERE id = :id");
    $stmt_target->execute([':id' => $user_id]);
    $target_user = $stmt_target->fetch(PDO::FETCH_ASSOC);

    if (!$target_user) {
        // 存在しないユーザー
        header('Location: users.php');
        exit();
    }
} catch (PDOException $e) {
    die("DB Error: " . h($e->getMessage()));
}

// 権限チェック: Staffは一般ユーザー(ROLE_USER)以外を操作できない
if ($_SESSION['user']['role'] != ROLE_MANAGER) {
    // ターゲットが管理者やスタッフの場合、アクセス拒否（または閲覧のみにするが、ここでは編集画面なので拒否が安全）
    if ($target_user['role'] != ROLE_USER) {
        $_SESSION['error_message'] = "権限がありません。スタッフは一般ユーザーのみ管理できます。";
        header('Location: users.php');
        exit();
    }
}

// ユーザー更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    validate_csrf_token();
    $name = filter_input(INPUT_POST, 'name');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone');
    $notes = filter_input(INPUT_POST, 'notes');

    // ロールの処理: マネージャー以外は変更不可（元の値を維持）
    if ($_SESSION['user']['role'] == ROLE_MANAGER) {
        $role = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);
    } else {
        $role = $target_user['role']; // 強制的に元の値を使用
    }

    // Role validation
    if ($name && $email && in_array($role, [ROLE_USER, ROLE_MANAGER, ROLE_STAFF, ROLE_CLEANER], true)) {

        try {
            $sql = "UPDATE users SET `name` = :name, `email` = :email, `phone` = :phone, `role` = :role, `notes` = :notes WHERE `id` = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_INT);
            $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = "ユーザー情報を更新しました。";

                // Log action
                log_admin_action($dbh, $_SESSION['user']['id'], 'update_user', [
                    'target_user_id' => $user_id,
                    'name' => $name,
                    'role' => $role
                ]);

                // 最新の状態に更新
                $target_user['name'] = $name;
                $target_user['email'] = $email;
                $target_user['phone'] = $phone;
                $target_user['role'] = $role;
                $target_user['notes'] = $notes;
            } else {
                $error = "更新に失敗しました。";
            }
        } catch (PDOException $e) {
            $error = "データベースエラー: " . h($e->getMessage());
        }
    } else {
        $error = "必須項目（氏名、メールアドレス）が正しく入力されていないか、不正な権限が選択されました。";
    }
}

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    validate_csrf_token();
    // 権限チェックは冒頭で行われているため、ここに来る時点で StaffはUserのみ操作可能

    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "パスワードを入力してください。";
    } elseif ($new_password !== $confirm_password) {
        $error = "パスワードが一致しません。";
    } elseif (strlen($new_password) < 8) {
        $error = "パスワードは8文字以上で入力してください。";
    } else {
        try {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':password', $password_hash, PDO::PARAM_STR);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = "パスワードを変更しました。";
                log_admin_action($dbh, $_SESSION['user']['id'], 'update_password', ['target_user_id' => $user_id]);
            } else {
                $error = "パスワード変更に失敗しました。";
            }
        } catch (PDOException $e) {
            $error = "データベースエラー: " . h($e->getMessage());
        }
    }
}

// ユーザー削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    validate_csrf_token();
    // 権限チェック済み (StaffはUserのみ)

    if ($user_id == $_SESSION['user']['id']) {
        $error = "自分自身を削除することはできません。";
    } else {
        try {
            $sql = "DELETE FROM users WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $_SESSION['message'] = "ユーザーID " . h($user_id) . " を削除しました。";
                log_admin_action($dbh, $_SESSION['user']['id'], 'delete_user', ['target_user_id' => $user_id]);
                header('Location: users.php');
                exit();
            } else {
                $error = "削除に失敗しました。";
            }
        } catch (PDOException $e) {
            $error = "データベースエラー: " . h($e->getMessage());
        }
    }
}

// ユーザー変数をview用にセット
$user = $target_user;

// 予約履歴取得
$user_bookings = [];
if ($user) {
    try {
        $sql = "SELECT
                    b.id,
                    r.name AS room_name,
                    b.check_in_date,
                    b.check_out_date,
                    b.total_price,
                    b.status
                FROM bookings AS b
                JOIN booking_rooms AS br ON b.id = br.booking_id
                JOIN rooms AS r ON br.room_id = r.id
                WHERE b.user_id = :user_id
                ORDER BY b.check_in_date DESC";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // 予約履歴取得失敗は致命的ではないためエラーメッセージを表示しつつ続行
        $error .= " 予約履歴の取得に失敗しました。";
    }
}

$csrf_token = generate_csrf_token();
require_once 'admin_header.php';
?>

<h2>顧客詳細・編集</h2>

<?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p style="color: red;"><?php echo $error; ?></p>
<?php endif; ?>

<?php if ($user): ?>
    <div style="display: flex; gap: 2rem;">
        <div style="flex: 1;">
            <h3>基本情報</h3>
            <form action="user_detail.php?id=<?php echo h($user_id); ?>" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <table style="width: 100%;">
                    <tr>
                        <th>ID</th>
                        <td><?php echo h($user['id']); ?></td>
                    </tr>
                    <tr>
                        <th>氏名 <span style="color:red;">*</span></th>
                        <td><input type="text" name="name" value="<?php echo h($user['name']); ?>" required style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <th>メールアドレス <span style="color:red;">*</span></th>
                        <td><input type="email" name="email" value="<?php echo h($user['email']); ?>" required style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <th>電話番号</th>
                        <td><input type="text" name="phone" value="<?php echo h($user['phone'] ?? ''); ?>" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <th>権限</th>
                        <td>
                            <?php if ($_SESSION['user']['role'] == ROLE_MANAGER): ?>
                                <select name="role">
                                    <option value="<?php echo ROLE_USER; ?>" <?php echo $user['role'] == ROLE_USER ? 'selected' : ''; ?>>一般ユーザー</option>
                                    <option value="<?php echo ROLE_MANAGER; ?>" <?php echo $user['role'] == ROLE_MANAGER ? 'selected' : ''; ?>>管理者 (Manager)</option>
                                    <option value="<?php echo ROLE_STAFF; ?>" <?php echo $user['role'] == ROLE_STAFF ? 'selected' : ''; ?>>フロント (Staff)</option>
                                    <option value="<?php echo ROLE_CLEANER; ?>" <?php echo $user['role'] == ROLE_CLEANER ? 'selected' : ''; ?>>清掃 (Cleaner)</option>
                                </select>
                            <?php else: ?>
                                <?php
                                    $role_name = '一般ユーザー';
                                    if ($user['role'] == ROLE_MANAGER) $role_name = '管理者';
                                    elseif ($user['role'] == ROLE_STAFF) $role_name = 'フロント';
                                    elseif ($user['role'] == ROLE_CLEANER) $role_name = '清掃';
                                    echo h($role_name);
                                ?>
                                <!-- スタッフは権限変更不可なのでhiddenでも送らない（サーバーサイドで無視するが、念のため） -->
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>特記事項 (Notes)</th>
                        <td><textarea name="notes" rows="5" style="width: 100%;"><?php echo h($user['notes'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th>登録日</th>
                        <td><?php echo h($user['created_at']); ?></td>
                    </tr>
                </table>
                <div style="margin-top: 1rem; text-align: right;">
                    <button type="submit" name="update_user" class="btn-admin" style="background-color: #27ae60;">更新する</button>
                </div>
            </form>

            <div style="margin-top: 2rem; border-top: 1px solid #ddd; padding-top: 1rem;">
                <h3>パスワード変更</h3>
                <form action="user_detail.php?id=<?php echo h($user_id); ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <table style="width: 100%;">
                        <tr>
                            <th>新しいパスワード</th>
                            <td><input type="password" name="new_password" required style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th>パスワード確認</th>
                            <td><input type="password" name="confirm_password" required style="width: 100%;"></td>
                        </tr>
                    </table>
                    <div style="margin-top: 1rem; text-align: right;">
                        <button type="submit" name="update_password" class="btn-admin" style="background-color: #f39c12;">変更する</button>
                    </div>
                </form>
            </div>

            <div style="margin-top: 2rem; border-top: 1px solid #ddd; padding-top: 1rem;">
                <h3>アカウント操作</h3>
                <form action="user_detail.php?id=<?php echo h($user_id); ?>" method="post" onsubmit="return confirm('本当にこのユーザーを削除しますか？\nこの操作は取り消せません。');">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <button type="submit" name="delete_user" class="btn-admin btn-cancel">このユーザーを削除</button>
                </form>
            </div>
        </div>

        <div style="flex: 1;">
            <h3>予約履歴</h3>
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>予約ID</th>
                        <th>部屋</th>
                        <th>チェックイン</th>
                        <th>ステータス</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($user_bookings)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">予約履歴はありません。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($user_bookings as $booking): ?>
                            <tr>
                                <td><?php echo h($booking['id']); ?></td>
                                <td><?php echo h($booking['room_name']); ?></td>
                                <td><?php echo h($booking['check_in_date']); ?></td>
                                <td>
                                    <span class="<?php echo $booking['status'] === 'confirmed' ? 'status-confirmed' : 'status-cancelled'; ?>">
                                        <?php echo h($booking['status'] === 'confirmed' ? '確定' : 'キャンセル'); ?>
                                    </span>
                                </td>
                                <td><a href="edit_booking.php?id=<?php echo h($booking['id']); ?>">確認</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <style>
    .status-confirmed { color: green; font-weight: bold; }
    .status-cancelled { color: red; text-decoration: line-through; }
    </style>
<?php endif; ?>

<?php
require_once 'admin_footer.php';
?>
