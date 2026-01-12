<?php
require_once 'includes/header.php';

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

<div class="max-w-md mx-auto my-12 bg-surface-light dark:bg-surface-dark p-8 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
    <h2 class="text-2xl font-bold mb-6 text-center text-gray-800 dark:text-white">パスワード変更</h2>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded bg-green-50 text-green-700 border border-green-200 text-center">
            <p class="mb-4"><?php echo h($message); ?></p>
            <a href="mypage.php" class="inline-block bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded shadow transition-colors duration-200">マイページに戻る</a>
        </div>
    <?php else: ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 rounded bg-red-50 text-red-700 border border-red-200">
                <p><?php echo h($error); ?></p>
            </div>
        <?php endif; ?>

        <form action="change_password.php" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

            <div>
                <label for="current_password" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">現在のパスワード</label>
                <input type="password" id="current_password" name="current_password" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
            </div>

            <div>
                <label for="new_password" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">新しいパスワード (8+文字)</label>
                <input type="password" id="new_password" name="new_password" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">新しいパスワード (確認)</label>
                <input type="password" id="confirm_password" name="confirm_password" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
            </div>

            <div class="flex justify-end gap-4 mt-8">
                <a href="mypage.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2.5 px-4 rounded-md shadow transition-colors duration-200">キャンセル</a>
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-2.5 px-4 rounded-md shadow transition-colors duration-200">変更する</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
