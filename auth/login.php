<?php
$root_path = '../';
require_once $root_path . 'includes/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // セッション固定攻撃対策
            $_SESSION['user'] = $user;

            // ロールによるリダイレクト振り分け
            if ($user['role'] == 1) {
                header('Location: ' . $root_path . 'admin/index.php');
            } else {
                header('Location: ' . $root_path . 'user/');
            }
            exit();
        } else {
            $errors[] = t('login_error_invalid');
        }
    } catch (PDOException $e) {
        $errors[] = t('error_db');
    }
}
$csrf_token = generate_csrf_token();
?>
<style>
.login-container {
    max-width: 400px;
    margin: 50px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; }
.form-group input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
.btn {
    display: inline-block;
    padding: 10px 20px;
    background-color: #5cb85c;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}
.btn:hover { background-color: #4cae4c; }
.error-message { color: red; margin-bottom: 15px; }
</style>

<div class="login-container">
    <h2><?php echo h(t('login_title')); ?></h2>
    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color: green;"><?php echo h(t($_SESSION['success_message']) ?? $_SESSION['success_message']); ?></p>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <p style="color: red;"><?php echo h(t($_SESSION['error_message']) ?? $_SESSION['error_message']); ?></p>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <p><?php echo h($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <div class="form-group">
            <label for="email"><?php echo h(t('form_email')); ?></label>
            <input type="email" id="email" name="email" value="<?php echo isset($email) ? h($email) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="password"><?php echo h(t('form_password')); ?></label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn"><?php echo h(t('btn_login')); ?></button>
    </form>
    <p><a href="register.php"><?php echo h(t('link_register')); ?></a></p>
</div>

<?php
require_once $root_path . 'includes/footer.php';
?>
