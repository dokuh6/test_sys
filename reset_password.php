<?php
require_once 'includes/init.php';

$errors = [];
$success_message = '';
$token = filter_input(INPUT_GET, 'token');
$valid_token = false;

// POSTの場合はフォームからトークンを取得
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = filter_input(INPUT_POST, 'token');
}

// トークンの検証
if ($token) {
    try {
        $stmt = $dbh->prepare("SELECT id FROM users WHERE reset_token = :token AND reset_expires_at > NOW()");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $valid_token = true;
        } else {
            $errors[] = t('error_invalid_token') . ' ' . t('error_token_expired');
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $errors[] = t('error_db');
    }
} else {
    $errors[] = t('error_invalid_token');
}

// パスワード更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    validate_csrf_token();

    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (strlen($password) < 8) {
        $errors[] = t('register_error_password_length');
    } elseif ($password !== $password_confirm) {
        $errors[] = t('register_error_password_mismatch');
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $update_stmt = $dbh->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_expires_at = NULL WHERE id = :id");
            $update_stmt->execute([
                ':password' => $hashed_password,
                ':id' => $user['id']
            ]);

            $success_message = t('reset_success_desc');
            $valid_token = false; // 処理完了後はフォームを隠すため
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = t('error_db');
        }
    }
}

require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto my-12 px-4">
    <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-lg p-8 border border-gray-200 dark:border-gray-700">
        <h2 class="text-2xl font-bold text-center text-gray-800 dark:text-white mb-6">
            <?php echo h(t('reset_password_title')); ?>
        </h2>

        <?php if ($success_message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 p-6 rounded-lg text-center mb-6">
                <h3 class="font-bold text-lg mb-2"><?php echo h(t('reset_success_title')); ?></h3>
                <p><?php echo h($success_message); ?></p>
            </div>
            <div class="text-center">
                <a href="login.php" class="inline-block bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-md shadow transition-colors duration-200">
                    <?php echo h(t('nav_login')); ?>
                </a>
            </div>
        <?php elseif ($valid_token): ?>
            <p class="text-gray-600 dark:text-gray-300 mb-6 text-center">
                <?php echo h(t('reset_password_desc')); ?>
            </p>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg mb-6">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="reset_password.php" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo h(generate_csrf_token()); ?>">
                <input type="hidden" name="token" value="<?php echo h($token); ?>">

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <?php echo h(t('form_password')); ?>
                    </label>
                    <input type="password" id="password" name="password" required minlength="8"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white">
                </div>

                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <?php echo h(t('form_password_confirm')); ?>
                    </label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white">
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-4 rounded-lg shadow transition-colors duration-200">
                    <?php echo h(t('btn_reset_password')); ?>
                </button>
            </form>
        <?php else: ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-6 rounded-lg text-center mb-6">
                <p>
                    <?php foreach ($errors as $error) echo h($error) . "<br>"; ?>
                </p>
            </div>
            <div class="text-center">
                <a href="forgot_password.php" class="text-primary hover:text-primary-dark font-medium hover:underline">
                    <?php echo h(t('forgot_password_title')); ?>へ戻る
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
