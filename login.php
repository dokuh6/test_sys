<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$errors = [];

// 登録完了メッセージがあれば取得
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password');

    if (!$email || empty($password)) {
        $errors[] = 'メールアドレスとパスワードを入力してください。';
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
                $errors[] = 'メールアドレスまたはパスワードが正しくありません。';
            }

        } catch (PDOException $e) {
            $errors[] = 'データベースエラーが発生しました。';
        }
    }
}


require_once 'includes/header.php';
?>
<style>
.form-container { max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; }
.form-group input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
.error-messages { color: red; border: 1px solid red; padding: 15px; margin-bottom: 20px; }
.success-message { color: green; border: 1px solid green; padding: 15px; margin-bottom: 20px; }
</style>

<div class="form-container">
    <h2>ログイン</h2>

    <?php if (!empty($success_message)): ?>
        <div class="success-message">
            <p><?php echo h($success_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error-messages">
            <?php foreach ($errors as $error): ?>
                <p><?php echo h($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="email">メールアドレス</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn">ログイン</button>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
