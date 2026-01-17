<?php
require_once 'admin_check.php';

// Check if Manager (Role 1) or Staff
require_permission([ROLE_MANAGER, ROLE_STAFF]);

$message = '';
$error = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// ユーザー一覧取得
$search_query = filter_input(INPUT_GET, 'q');
try {
    $sql = "SELECT `id`, `name`, `email`, `phone`, `role`, `created_at` FROM users";
    $where = [];
    $params = [];

    if ($search_query) {
        $where[] = "(name LIKE :q OR email LIKE :q)";
        $params[':q'] = "%" . $search_query . "%";
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY id DESC";

    $stmt = $dbh->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
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
                    <td>
                        <?php
                        switch ($user['role']) {
                            case ROLE_MANAGER: echo '管理者'; break;
                            case ROLE_STAFF: echo 'フロント'; break;
                            case ROLE_CLEANER: echo '清掃'; break;
                            default: echo '一般'; break;
                        }
                        ?>
                    </td>
                    <td><?php echo h(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                    <td>
                        <?php
                        // スタッフは一般ユーザーのみ編集可能
                        $can_edit = true;
                        if ($_SESSION['user']['role'] != ROLE_MANAGER && $user['role'] != ROLE_USER) {
                            $can_edit = false;
                        }
                        ?>
                        <?php if ($can_edit): ?>
                            <a href="user_detail.php?id=<?php echo h($user['id']); ?>" class="btn-admin" style="background-color:#3498db;">詳細・編集</a>
                        <?php else: ?>
                            <span style="color:gray;">権限なし</span>
                        <?php endif; ?>
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
