<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォームからの入力を取得
    $name = filter_input(INPUT_POST, 'name');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password');
    $password_confirm = filter_input(INPUT_POST, 'password_confirm');

    // バリデーション
    if (empty($name)) {
        $errors[] = '氏名を入力してください。';
    }
    if (!$email) {
        $errors[] = '有効なメールアドレスを入力してください。';
    }
    if (empty($password) || mb_strlen($password) < 8) {
        $errors[] = 'パスワードは8文字以上で入力してください。';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'パスワードが一致しません。';
    }

    // メールアドレスの重複チェック
    if (empty($errors)) {
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'このメールアドレスは既に使用されています。';
            }
        } catch (PDOException $e) {
            $errors[] = 'データベースエラーが発生しました。';
        }
    }

    // エラーがなければユーザー登録
    if (empty($errors)) {
        try {
            // パスワードをハッシュ化
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, 0)";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);

            if ($stmt->execute()) {
                // 登録成功
                $_SESSION['success_message'] = "会員登録が完了しました。ログインしてください。";
                header('Location: login.php');
                exit();
            } else {
                $errors[] = 'ユーザー登録に失敗しました。';
            }

        } catch (PDOException $e) {
            $errors[] = 'データベースエラー: ' . h($e->getMessage());
        }
    }
}


require_once 'includes/header.php';
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
    <h2>会員登録</h2>

    <?php if (!empty($errors)): ?>
        <div class="error-messages">
            <?php foreach ($errors as $error): ?>
                <p><?php echo h($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="name">氏名</label>
            <input type="text" id="name" name="name" value="<?php echo isset($name) ? h($name) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="email">メールアドレス</label>
            <input type="email" id="email" name="email" value="<?php echo isset($email) ? h($email) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="password">パスワード (8文字以上)</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="password_confirm">パスワード（確認用）</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        <button type="submit" class="btn">登録する</button>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
