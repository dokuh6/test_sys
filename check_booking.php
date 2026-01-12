<?php
require_once 'includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div style="max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
    <h2><?php echo h(t('check_booking_title') ?? '予約確認'); ?></h2>
    <p><?php echo h(t('check_booking_desc') ?? '予約番号とメールアドレスを入力して、予約内容を確認できます。'); ?></p>

    <form action="confirm.php" method="GET">
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
require_once 'includes/footer.php';
?>
