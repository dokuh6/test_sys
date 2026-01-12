<?php
require_once 'includes/header.php';

$errors = [];

// 登録完了メッセージがあれば取得
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password');

    if (!$email || empty($password)) {
        $errors[] = t('login_error_no_input');
    } else {
        try {
            // メールアドレスでユーザーを検索
            $sql = "SELECT * FROM users WHERE email = :email";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // ユーザーが存在し、パスワードが一致するか検証
            if ($user && password_verify($password, $user['password'])) {
                // ログイン成功
                session_regenerate_id(true); // セッション固定化攻撃対策
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ];

                // 管理者であれば管理者ページへ、一般ユーザーはマイページへ
                if ($user['role'] == 1) {
                    header('Location: admin/index.php');
                } else {
                    header('Location: mypage.php');
                }
                exit();
            } else {
                $errors[] = t('login_error_wrong_credentials');
            }

        } catch (PDOException $e) {
            $errors[] = t('error_db');
        }
    }
}

$csrf_token = generate_csrf_token();
?>

<main class="flex-grow flex items-center justify-center py-20 px-4">
    <div class="w-full max-w-md bg-white dark:bg-[#1a301d] rounded-2xl shadow-xl p-8 border border-gray-100 dark:border-[#223a26]">
        <h2 class="text-2xl font-bold text-center mb-8 text-[#0d1b10] dark:text-white"><?php echo h(t('login_title')); ?></h2>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-50 dark:bg-green-900/10 border border-green-100 dark:border-green-900/20 rounded-lg p-4 mb-6">
                <p class="text-green-700 dark:text-green-400 text-sm font-bold text-center"><?php echo h(t($success_message)); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/20 rounded-lg p-4 mb-6">
                <?php foreach ($errors as $error): ?>
                    <p class="text-red-600 dark:text-red-400 text-sm text-center"><?php echo h($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <div>
                <label for="email" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_email')); ?></label>
                <input type="email" id="email" name="email" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
            </div>
            <div>
                <label for="password" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_password')); ?></label>
                <input type="password" id="password" name="password" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
            </div>
            <button type="submit" class="w-full bg-primary hover:bg-opacity-90 text-[#0d1b10] py-3 rounded-xl font-bold text-lg transition-all shadow-lg shadow-primary/20">
                <?php echo h(t('btn_login')); ?>
            </button>
        </form>

        <div class="mt-8 text-center space-y-2">
            <p class="text-sm text-gray-500">
                <a href="register.php" class="text-primary font-bold hover:underline"><?php echo h(t('nav_register')); ?></a>
            </p>
        </div>
    </div>
</main>

<?php
require_once 'includes/footer.php';
?>
