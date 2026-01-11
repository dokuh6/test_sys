<?php
require_once 'admin_check.php';

// ページネーション設定
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // 総件数取得
    $stmt = $dbh->query("SELECT COUNT(*) FROM email_logs");
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $limit);

    // ログ取得
    $sql = "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("データベースエラー: " . h($e->getMessage()));
}

require_once 'admin_header.php';
?>

<h2>送信メールログ</h2>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>送信日時</th>
            <th>宛先</th>
            <th>件名</th>
            <th>ステータス</th>
            <th>詳細</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr>
                <td colspan="6" style="text-align: center;">ログはありません。</td>
            </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo h($log['id']); ?></td>
                    <td><?php echo h($log['created_at']); ?></td>
                    <td><?php echo h($log['to_email']); ?></td>
                    <td><?php echo h($log['subject']); ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span style="color: green; font-weight: bold;">成功</span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">失敗</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="email_log_detail.php?id=<?php echo h($log['id']); ?>" class="btn-admin" style="background-color: #3498db;">詳細</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- ページネーション -->
<?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top: 20px; text-align: center;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span style="margin: 0 5px; font-weight: bold;"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>" style="margin: 0 5px;"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php
require_once 'admin_footer.php';
?>
