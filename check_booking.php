<?php
require_once 'includes/header.php';
$csrf_token = generate_csrf_token();
?>

<main class="flex-grow flex items-center justify-center py-20 px-4">
    <div class="w-full max-w-md bg-white dark:bg-[#1a301d] rounded-2xl shadow-xl p-8 border border-gray-100 dark:border-[#223a26]">
        <h2 class="text-2xl font-bold text-center mb-4 text-[#0d1b10] dark:text-white"><?php echo h(t('check_booking_title') ?? '予約確認'); ?></h2>
        <p class="text-center text-gray-500 text-sm mb-8"><?php echo h(t('check_booking_desc') ?? '予約番号とメールアドレスを入力して、予約内容を確認できます。'); ?></p>

        <form action="confirm.php" method="GET" class="space-y-6">
            <div>
                <label for="booking_number" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2">
                    <?php echo h(t('booking_number') ?? '予約番号'); ?> <span class="text-xs font-normal text-gray-400">(YYYYMMDD-XXXXXXXX)</span>
                </label>
                <input type="text" id="booking_number" name="booking_number" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all font-mono placeholder-gray-400" placeholder="20240101-ABCDEF12">
            </div>

            <div>
                <label for="guest_email" class="block text-sm font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('form_email')); ?></label>
                <input type="email" id="guest_email" name="guest_email" required class="w-full bg-[#f6f8f6] dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-opacity-90 text-[#0d1b10] py-3 rounded-xl font-bold text-lg transition-all shadow-lg shadow-primary/20">
                <?php echo h(t('btn_check_booking') ?? '確認する'); ?>
            </button>
        </form>
    </div>
</main>

<?php
require_once 'includes/footer.php';
?>
