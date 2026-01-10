<?php
require_once 'admin_check.php';
require_once 'admin_header.php';

// ページネーション設定
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 総件数取得
$sql_count = "SELECT COUNT(*) FROM email_logs";
$total_logs = $dbh->query($sql_count)->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// ログ取得
$sql = "SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT :limit OFFSET :offset";
$stmt = $dbh->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">メール送信履歴</h1>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>送信先</th>
                    <th>件名</th>
                    <th>送信日時</th>
                    <th>ステータス</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo h($log['id']); ?></td>
                            <td><?php echo h($log['to_email']); ?></td>
                            <td><?php echo h($log['subject']); ?></td>
                            <td><?php echo h($log['sent_at']); ?></td>
                            <td>
                                <?php if ($log['status'] == 'success'): ?>
                                    <span class="badge bg-success">成功</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">失敗</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="email_log_detail.php?id=<?php echo h($log['id']); ?>" class="btn btn-sm btn-outline-primary">詳細</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">履歴はありません。</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ページネーション -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">前へ</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">次へ</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
