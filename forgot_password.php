<?php
require_once 'includes/init.php';

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $errors[] = t('register_error_email');
    } else {
        try {
            // メールアドレスの存在確認
            $stmt = $dbh->prepare("SELECT id, name FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // トークン生成
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // DB更新
                $update_stmt = $dbh->prepare("UPDATE users SET reset_token = :token, reset_expires_at = :expires_at WHERE id = :id");
                $update_stmt->execute([
                    ':token' => $token,
                    ':expires_at' => $expires_at,
                    ':id' => $user['id']
                ]);

                // メール送信
                $reset_link = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;

                $subject = t('email_subject_reset_password');
                $body = t('email_body_reset_password') . "\n\n" . $reset_link;

                // HTMLメール用テンプレート
                $html_body = "
                <html>
                <body style='font-family: sans-serif; line-height: 1.6;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                        <h2 style='color: #333;'>" . t('reset_password_title') . "</h2>
                        <p>" . t('email_body_reset_password') . "</p>
                        <p style='margin: 20px 0;'>
                            <a href='" . $reset_link . "' style='background-color: #0f4c81; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>" . t('reset_password_title') . "</a>
                        </p>
                        <p style='font-size: 12px; color: #777;'>" . $reset_link . "</p>
                        <p style='font-size: 12px; color: #999; margin-top: 30px;'>" . t('email_footer_auto') . "</p>
                    </div>
                </body>
                </html>
                ";

                if (send_email_smtp($email, $subject, $html_body, $dbh, true)) {
                    $success_message = t('email_sent_desc');
                } else {
                    $errors[] = t('error_db'); // メール送信失敗もDBエラーとして扱うか、別途メッセージを用意するか
                }
            } else {
                // セキュリティのため、存在しない場合も送信したように見せるのが一般的だが、
                // 要件やキー名(error_email_not_found)に従いエラーを表示
                $errors[] = t('error_email_not_found');
            }

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
            <?php echo h(t('forgot_password_title')); ?>
        </h2>

        <?php if ($success_message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 p-6 rounded-lg text-center mb-6">
                <h3 class="font-bold text-lg mb-2"><?php echo h(t('email_sent_title')); ?></h3>
                <p><?php echo h($success_message); ?></p>
            </div>
            <div class="text-center">
                <a href="login.php" class="text-primary hover:text-primary-dark font-medium hover:underline">
                    <?php echo h(t('login_title')); ?>へ戻る
                </a>
            </div>
        <?php else: ?>
            <p class="text-gray-600 dark:text-gray-300 mb-6 text-center">
                <?php echo h(t('forgot_password_desc')); ?>
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

            <form method="POST" action="forgot_password.php" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo h(generate_csrf_token()); ?>">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <?php echo h(t('form_email')); ?>
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white"
                           placeholder="your@email.com">
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-4 rounded-lg shadow transition-colors duration-200">
                    <?php echo h(t('btn_send_reset_link')); ?>
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="login.php" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:underline">
                    <?php echo h(t('login_title')); ?>へ戻る
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
