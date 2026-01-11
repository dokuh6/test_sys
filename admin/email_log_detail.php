<?php
require_once 'admin_check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: email_logs.php");
    exit;
}

try {
    $stmt = $dbh->prepare("SELECT * FROM email_logs WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        die("指定されたログが見つかりません。");
    }

} catch (PDOException $e) {
    die("データベースエラー: " . h($e->getMessage()));
}

require_once 'admin_header.php';
?>

<h2>メールログ詳細</h2>

<div style="background-color: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
    <p><strong>ID:</strong> <?php echo h($log['id']); ?></p>
    <p><strong>送信日時:</strong> <?php echo h($log['created_at']); ?></p>
    <p><strong>ステータス:</strong>
        <?php if ($log['status'] === 'success'): ?>
            <span style="color: green; font-weight: bold;">成功</span>
        <?php else: ?>
            <span style="color: red; font-weight: bold;">失敗</span>
        <?php endif; ?>
    </p>
    <p><strong>宛先:</strong> <?php echo h($log['to_email']); ?></p>
    <p><strong>件名:</strong> <?php echo h($log['subject']); ?></p>

    <?php if ($log['error_message']): ?>
        <div style="background-color: #fce8e6; padding: 10px; border: 1px solid #d9534f; margin-bottom: 15px;">
            <strong>エラーメッセージ:</strong><br>
            <pre style="white-space: pre-wrap; color: #d9534f;"><?php echo h($log['error_message']); ?></pre>
        </div>
    <?php endif; ?>

    <hr>
    <h3>本文</h3>
    <div style="background-color: #f9f9f9; padding: 15px; border: 1px solid #eee; overflow-x: auto;">
        <pre style="white-space: pre-wrap; font-family: monospace;"><?php echo h($log['body']); ?></pre>
    </div>
</div>

<p style="margin-top: 20px;">
    <a href="email_logs.php" class="btn-admin" style="background-color: #7f8c8d;">一覧に戻る</a>
</p>

<?php
require_once 'admin_footer.php';
?>
