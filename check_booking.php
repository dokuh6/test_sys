<?php
require_once 'includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="max-w-xl mx-auto my-12 bg-surface-light dark:bg-surface-dark p-8 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
    <h2 class="text-2xl font-bold mb-4 text-center text-gray-800 dark:text-white"><?php echo h(t('check_booking_title') ?? '予約確認'); ?></h2>
    <p class="text-gray-600 dark:text-gray-300 text-center mb-8"><?php echo h(t('check_booking_desc') ?? '予約番号とメールアドレスを入力して、予約内容を確認できます。'); ?></p>

    <form action="confirm.php" method="GET" class="space-y-6">
        <div>
            <label for="booking_number" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('booking_number') ?? '予約番号'); ?> (YYYYMMDD-XXXXXXXX)</label>
            <input type="text" id="booking_number" name="booking_number" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
        </div>

        <div>
            <label for="guest_email" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_email')); ?></label>
            <input type="email" id="guest_email" name="guest_email" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3">
        </div>

        <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2.5 px-4 rounded-md shadow transition-colors duration-200">
            <?php echo h(t('btn_check_booking') ?? '確認する'); ?>
        </button>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
