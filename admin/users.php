<?php
require_once 'admin_check.php';

$message = '';
$error = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// ユーザー一覧取得
$search_query = filter_input(INPUT_GET, 'q');
try {
    $sql = "SELECT `id`, `name`, `email`, `phone`, `role`, `created_at` FROM users";
    if ($search_query) {
        $sql .= " WHERE name LIKE :q OR email LIKE :q";
    }
    $sql .= " ORDER BY id DESC";

    $stmt = $dbh->prepare($sql);
    if ($search_query) {
        $like_query = "%" . $search_query . "%";
        $stmt->bindParam(':q', $like_query, PDO::PARAM_STR);
    }
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "ユーザー情報の取得に失敗しました: " . h($e->getMessage());
    $users = [];
}

require_once 'admin_header.php';
?>

<h2>顧客管理</h2>

<?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p style="color: red;"><?php echo $error; ?></p>
<?php endif; ?>

<div style="margin-bottom: 1rem;">
    <form action="users.php" method="get">
        <input type="text" name="q" placeholder="氏名またはメールアドレスで検索" value="<?php echo h($search_query); ?>">
        <button type="submit" class="btn-admin" style="background-color: #7f8c8d;">検索</button>
    </form>
</div>

<div class="table-responsive">
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>氏名</th>
            <th>メールアドレス</th>
            <th>電話番号</th>
            <th>権限</th>
            <th>登録日</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="7" style="text-align: center;">ユーザーが見つかりません。</td>
            </tr>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo h($user['id']); ?></td>
                    <td><?php echo h($user['name']); ?></td>
                    <td><?php echo h($user['email']); ?></td>
                    <td><?php echo h($user['phone']); ?></td>
                    <td><?php echo h($user['role'] == 1 ? '管理者' : '一般'); ?></td>
                    <td><?php echo h(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                    <td>
                        <a href="user_detail.php?id=<?php echo h($user['id']); ?>" class="btn-admin" style="background-color:#3498db;">詳細・編集</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php
require_once 'admin_footer.php';
?>
