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

            $sql = "INSERT INTO users (`name`, `email`, `password`, `role`) VALUES (:name, :email, :password, 0)";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);

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
<style>
.form-container {
    max-width: 500px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; }
.form-group input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
.error-messages { color: red; border: 1px solid red; padding: 15px; margin-bottom: 20px; }
</style>

<div class="form-container">
    <h2><?php echo h(t('register_title')); ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="error-messages">
            <?php foreach ($errors as $error): ?>
                <p><?php echo h($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <div class="form-group">
            <label for="name"><?php echo h(t('form_name')); ?></label>
            <input type="text" id="name" name="name" value="<?php echo isset($name) ? h($name) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="email"><?php echo h(t('form_email')); ?></label>
            <input type="email" id="email" name="email" value="<?php echo isset($email) ? h($email) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="password"><?php echo h(t('form_password')); ?> (8+ characters)</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="password_confirm"><?php echo h(t('form_password_confirm')); ?></label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        <button type="submit" class="btn"><?php echo h(t('btn_register')); ?></button>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
