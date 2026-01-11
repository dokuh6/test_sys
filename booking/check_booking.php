<?php
$root_path = '../';
require_once $root_path . 'includes/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 簡易的な予約確認ロジック
    // メールアドレスと予約番号で検索
    $booking_number = $_POST['booking_number'];
    $email = $_POST['guest_email'];

    if ($booking_number && $email) {
        // DBチェックして存在すれば詳細ページ(cancel_booking.phpを詳細ページとして兼用、または別途detail.phpを作る)へ
        // ここでは cancel_booking.php に飛ばして詳細表示・キャンセルを行わせるフローとする
        header("Location: cancel_booking.php?booking_number=" . urlencode($booking_number) . "&email=" . urlencode($email));
        exit();
    } else {
        $errors[] = t('error_all_fields_required');
    }
}
?>

<div style="max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
    <h2><?php echo h(t('check_booking_title') ?? '予約確認'); ?></h2>
    <p><?php echo h(t('check_booking_desc') ?? '予約番号とメールアドレスを入力して予約状況を確認します。'); ?></p>

    <?php if (!empty($errors)): ?>
        <div style="color: red; margin-bottom: 15px;">
            <?php foreach ($errors as $e) echo "<p>" . h($e) . "</p>"; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="form-group" style="margin-bottom: 15px;">
            <label for="booking_number" style="display: block; margin-bottom: 5px;"><?php echo h(t('booking_number') ?? '予約番号'); ?> (YYYYMMDD-XXXXXXXX)</label>
            <input type="text" id="booking_number" name="booking_number" required style="width: 100%; padding: 8px;">
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label for="guest_email" style="display: block; margin-bottom: 5px;"><?php echo h(t('form_email')); ?></label>
            <input type="email" id="guest_email" name="guest_email" required style="width: 100%; padding: 8px;">
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn" style="padding: 10px 20px;"><?php echo h(t('btn_check_booking') ?? '確認する'); ?></button>
        </div>
    </form>
</div>

<?php
require_once $root_path . 'includes/footer.php';
?>
