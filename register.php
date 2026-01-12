<?php
require_once 'includes/header.php';

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    // フォームからの入力を取得
    $name = filter_input(INPUT_POST, 'name');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password');
    $password_confirm = filter_input(INPUT_POST, 'password_confirm');
    $phone = filter_input(INPUT_POST, 'phone');

    // バリデーション
    if (empty($name)) {
        $errors[] = t('register_error_name');
    }
    if (!$email) {
        $errors[] = t('register_error_email');
    }
    if (empty($password) || mb_strlen($password) < 8) {
        $errors[] = t('register_error_password_length');
    }
    if ($password !== $password_confirm) {
        $errors[] = t('register_error_password_mismatch');
    }

    if ($phone && mb_strlen($phone) > 20) {
        $errors[] = '電話番号は20文字以内で入力してください。';
    }

    // メールアドレスの重複チェック
    if (empty($errors)) {
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $errors[] = t('register_error_email_in_use');
            }
        } catch (PDOException $e) {
            $errors[] = t('error_db') . ' (Select check failed: ' . $e->getMessage() . ')';
        }
    }

    // エラーがなければユーザー登録
    if (empty($errors)) {
        try {
            // パスワードをハッシュ化
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $current_time = date('Y-m-d H:i:s');
            $notes_default = "";
            $phone_val = $phone ? $phone : "";

            $sql = "INSERT INTO users (`name`, `email`, `password`, `phone`, `role`, `notes`, `created_at`, `updated_at`)
                    VALUES (:name, :email, :password, :phone, 0, :notes, :created_at, :updated_at)";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone_val, PDO::PARAM_STR);
            $stmt->bindParam(':notes', $notes_default, PDO::PARAM_STR);
            $stmt->bindParam(':created_at', $current_time, PDO::PARAM_STR);
            $stmt->bindParam(':updated_at', $current_time, PDO::PARAM_STR);

            if ($stmt->execute()) {
                // 登録成功
                $_SESSION['success_message'] = "login_success_message";
                header('Location: login.php');
                exit();
            } else {
                $errors[] = t('register_error_failed');
            }

        } catch (PDOException $e) {
            $errors[] = t('error_db') . ' (Insert failed: ' . $e->getMessage() . ')';
        }
    }
}

$csrf_token = generate_csrf_token();
?>

<main class="flex-grow flex items-center justify-center py-20 px-4">
    <div class="w-full max-w-md bg-white dark:bg-[#1a301d] rounded-2xl shadow-xl p-8 border border-gray-100 dark:border-[#223a26]">
        <h2 class="text-2xl font-bold text-center mb-8 text-[#0d1b10] dark:text-white"><?php echo h(t('register_title')); ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/20 rounded-lg p-4 mb-6">
                <?php foreach ($errors as $error): ?>
                    <p class="text-red-600 dark:text-red-400 text-sm text-center"><?php echo h($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <div>
                <label for="name" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_name')); ?></label>
                <input type="text" id="name" name="name" value="<?php echo isset($name) ? h($name) : ''; ?>" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
            </div>
            <div>
                <label for="email" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_email')); ?></label>
                <input type="email" id="email" name="email" value="<?php echo isset($email) ? h($email) : ''; ?>" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
            </div>
            <div>
                <label for="phone" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_tel')); ?></label>
                <input type="text" id="phone" name="phone" value="<?php echo isset($phone) ? h($phone) : ''; ?>" class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
            </div>
            <div>
                <label for="password" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_password')); ?> (8+ characters)</label>
                <input type="password" id="password" name="password" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
            </div>
            <div>
                <label for="password_confirm" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_password_confirm')); ?></label>
                <input type="password" id="password_confirm" name="password_confirm" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
            </div>
            <button type="submit" class="w-full bg-primary hover:bg-opacity-90 text-[#0d1b10] py-3 rounded-xl font-bold text-lg transition-all shadow-lg shadow-primary/20">
                <?php echo h(t('btn_register')); ?>
            </button>
        </form>

        <div class="mt-8 text-center space-y-2">
            <p class="text-sm text-gray-500">
                Already have an account? <a href="login.php" class="text-primary font-bold hover:underline"><?php echo h(t('nav_login')); ?></a>
            </p>
        </div>
    </div>
</main>

<?php
require_once 'includes/footer.php';
?>
