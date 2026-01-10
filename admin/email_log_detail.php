<?php
require_once 'includes/admin_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $stmt = $dbh->prepare("SELECT * FROM email_logs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$log) {
    echo '<div class="alert alert-danger">指定された履歴が見つかりません。</div>';
    require_once 'includes/admin_footer.php';
    exit;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">メール詳細</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="email_logs.php" class="btn btn-sm btn-outline-secondary">
                戻る
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>件名:</strong> <?php echo h($log['subject']); ?>
        </div>
        <div class="card-body">
            <div class="mb-3 row">
                <label class="col-sm-2 col-form-label fw-bold">送信日時</label>
                <div class="col-sm-10">
                    <input type="text" readonly class="form-control-plaintext" value="<?php echo h($log['sent_at']); ?>">
                </div>
            </div>
            <div class="mb-3 row">
                <label class="col-sm-2 col-form-label fw-bold">送信先</label>
                <div class="col-sm-10">
                    <input type="text" readonly class="form-control-plaintext" value="<?php echo h($log['to_email']); ?>">
                </div>
            </div>
            <div class="mb-3 row">
                <label class="col-sm-2 col-form-label fw-bold">ステータス</label>
                <div class="col-sm-10">
                     <?php if ($log['status'] == 'success'): ?>
                        <span class="badge bg-success">成功</span>
                    <?php else: ?>
                        <span class="badge bg-danger">失敗</span>
                        <div class="text-danger mt-1">
                            <small><?php echo h($log['error_message']); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <hr>

            <div class="mb-3">
                <label class="form-label fw-bold">本文</label>
                <div class="border p-3 bg-light" style="white-space: pre-wrap; font-family: monospace;"><?php echo h($log['body']); ?></div>
            </div>

             <div class="mb-3">
                 <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#headerDetails" aria-expanded="false" aria-controls="headerDetails">
                    ヘッダー情報を表示
                  </button>
                <div class="collapse mt-2" id="headerDetails">
                  <div class="card card-body bg-light" style="font-family: monospace; font-size: 0.8em;">
                    <?php echo nl2br(h($log['headers'])); ?>
                  </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
