<?php
$root_path = '../';
require_once $root_path . 'includes/header.php';

// ログインチェック
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = "パスワードを変更するにはログインが必要です。";
    header('Location: login.php');
    exit();
}

$csrf_token = generate_csrf_token();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "すべてのフィールドを入力してください。";
    } elseif ($new_password !== $confirm_password) {
        $error = "新しいパスワードと確認用パスワードが一致しません。";
    } elseif (strlen($new_password) < 8) {
        $error = "新しいパスワードは8文字以上で入力してください。";
    } else {
        // 現在のパスワードを確認
        try {
            $sql = "SELECT password FROM users WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':id', $_SESSION['user']['id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($current_password, $user['password'])) {
                // パスワード更新
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = :password WHERE id = :id";
                $update_stmt = $dbh->prepare($update_sql);
                $update_stmt->bindParam(':password', $new_password_hash, PDO::PARAM_STR);
                $update_stmt->bindParam(':id', $_SESSION['user']['id'], PDO::PARAM_INT);

                if ($update_stmt->execute()) {
                    $message = "パスワードが正常に変更されました。";
                } else {
                    $error = "パスワードの更新に失敗しました。";
                }
            } else {
                $error = "現在のパスワードが正しくありません。";
            }
        } catch (PDOException $e) {
            $error = "データベースエラー: " . h($e->getMessage());
        }
    }
}
?>

<div style="max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
    <h2>パスワード変更</h2>

    <?php if ($message): ?>
        <p style="color: green; background-color: #dff0d8; padding: 10px; border: 1px solid #d6e9c6;"><?php echo h($message); ?></p>
        <p><a href="<?php echo $root_path; ?>user/">マイページに戻る</a></p>
    <?php else: ?>

        <?php if ($error): ?>
            <p style="color: red; background-color: #f2dede; padding: 10px; border: 1px solid #ebccd1;"><?php echo h($error); ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="current_password" style="display: block; margin-bottom: 5px;">現在のパスワード</label>
                <input type="password" id="current_password" name="current_password" required style="width: 100%; padding: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="new_password" style="display: block; margin-bottom: 5px;">新しいパスワード (8文字以上)</label>
                <input type="password" id="new_password" name="new_password" required style="width: 100%; padding: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="confirm_password" style="display: block; margin-bottom: 5px;">新しいパスワード (確認)</label>
                <input type="password" id="confirm_password" name="confirm_password" required style="width: 100%; padding: 8px;">
            </div>

            <div style="text-align: right;">
                <a href="<?php echo $root_path; ?>user/" class="btn" style="background-color: #888; margin-right: 10px; text-decoration: none; padding: 8px 12px; color: white; border-radius: 4px;">キャンセル</a>
                <button type="submit" class="btn" style="padding: 8px 12px; border-radius: 4px;">変更する</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php
require_once $root_path . 'includes/footer.php';
?>
