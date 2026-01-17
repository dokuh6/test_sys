<?php
require_once 'includes/init.php';

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
                // Update session to match user_id check in header
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ];

                // ログ記録
                log_admin_action($dbh, $user['id'], 'login', ['ip' => $_SERVER['REMOTE_ADDR']]);

                // 管理者・スタッフ・清掃員であれば管理者ページへ、一般ユーザーはマイページへ
                if ($user['role'] == ROLE_MANAGER || $user['role'] == ROLE_STAFF || $user['role'] == ROLE_CLEANER) {
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
require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto my-12 bg-surface-light dark:bg-surface-dark p-8 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
    <h2 class="text-2xl font-bold mb-6 text-center text-gray-800 dark:text-white"><?php echo h(t('login_title')); ?></h2>

    <?php if (!empty($success_message)): ?>
        <div class="mb-6 p-4 rounded bg-green-50 text-green-700 border border-green-200 text-center">
            <p><?php echo h(t($success_message)); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded bg-red-50 text-red-700 border border-red-200">
            <?php foreach ($errors as $error): ?>
                <p class="mb-1 last:mb-0"><?php echo h($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_email')); ?></label>
            <input type="email" id="email" name="email" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_password')); ?></label>
            <input type="password" id="password" name="password" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
        </div>
        <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2.5 px-4 rounded-md shadow transition-colors duration-200">
            <?php echo h(t('btn_login')); ?>
        </button>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
