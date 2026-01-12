<?php
// db_connect and functions are loaded via language.php in header
require_once 'includes/header.php';

try {
    // 部屋タイプと結合し、各部屋のメイン画像も取得するSQL
    $sql = "SELECT
                r.id,
                r.name,
                r.name_en,
                r.price,
                rt.name AS type_name,
                rt.name_en AS type_name_en,
                rt.capacity,
                rt.description,
                rt.description_en,
                (SELECT image_path FROM room_images WHERE room_id = r.id AND is_main = 1 LIMIT 1) AS main_image
            FROM rooms AS r
            JOIN room_types AS rt ON r.room_type_id = rt.id
            ORDER BY r.price ASC";

    $stmt = $dbh->query($sql);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='text-red-500 p-4'>エラー: " . h($e->getMessage()) . "</div>";
    $rooms = [];
}
?>

<main class="max-w-[1280px] mx-auto w-full px-4 lg:px-10 py-12">

    <!-- Search Section -->
    <section class="mb-12 bg-white dark:bg-[#1a301d] rounded-2xl shadow-sm border border-gray-100 dark:border-[#223a26] p-8">
        <h3 class="text-2xl font-bold text-[#0d1b10] dark:text-white mb-6 text-center"><?php echo h(t('index_search_title')); ?></h3>
        <form action="search_results.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
            <div>
                <label for="check_in_date" class="block text-xs font-bold uppercase text-gray-500 mb-2"><?php echo h(t('form_check_in')); ?></label>
                <input type="date" id="check_in_date" name="check_in_date" value="<?php echo isset($_GET['check_in_date']) ? h($_GET['check_in_date']) : ''; ?>" required class="w-full bg-background-light dark:bg-[#102213] border-none rounded-lg px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-primary text-[#0d1b10] dark:text-white">
            </div>
            <div>
                <label for="check_out_date" class="block text-xs font-bold uppercase text-gray-500 mb-2"><?php echo h(t('form_check_out')); ?></label>
                <input type="date" id="check_out_date" name="check_out_date" value="<?php echo isset($_GET['check_out_date']) ? h($_GET['check_out_date']) : ''; ?>" required class="w-full bg-background-light dark:bg-[#102213] border-none rounded-lg px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-primary text-[#0d1b10] dark:text-white">
            </div>
            <div>
                <label for="num_guests" class="block text-xs font-bold uppercase text-gray-500 mb-2"><?php echo h(t('form_num_guests')); ?></label>
                <input type="number" id="num_guests" name="num_guests" min="1" value="<?php echo isset($_GET['num_guests']) ? h($_GET['num_guests']) : '1'; ?>" required class="w-full bg-background-light dark:bg-[#102213] border-none rounded-lg px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-primary text-[#0d1b10] dark:text-white">
            </div>
            <div>
                <button type="submit" class="w-full bg-primary hover:bg-opacity-90 text-[#0d1b10] py-3 rounded-lg font-bold text-sm transition-all shadow-md shadow-primary/20">
                    <?php echo h(t('btn_search_again')); ?>
                </button>
            </div>
        </form>
    </section>

    <h2 class="text-3xl font-extrabold text-[#0d1b10] dark:text-white mb-8"><?php echo h(t('rooms_list_title')); ?></h2>

    <?php if (!empty($rooms)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($rooms as $room): ?>
                <?php
                    // 言語対応
                    $r_name = ($current_lang === 'en' && !empty($room['name_en'])) ? $room['name_en'] : $room['name'];
                    $r_desc = ($current_lang === 'en' && !empty($room['description_en'])) ? $room['description_en'] : $room['description'];
                    $r_capacity = t('room_capacity_people', $room['capacity']);
                    $r_image = !empty($room['main_image']) ? $room['main_image'] : 'https://via.placeholder.com/800x600?text=No+Image';
                ?>
                <!-- Card Template -->
                <div class="bg-white dark:bg-[#1a301d] rounded-2xl shadow-lg border border-gray-100 dark:border-[#223a26] overflow-hidden flex flex-col group hover:shadow-xl transition-all duration-300 h-full">
                    <div class="relative h-64 overflow-hidden">
                        <img src="<?php echo h($r_image); ?>" alt="<?php echo h($r_name); ?>" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute top-4 right-4 bg-white/90 dark:bg-black/80 px-3 py-1.5 rounded-full text-xs font-bold text-[#0d1b10] dark:text-white backdrop-blur-md shadow-sm">
                            <?php echo h(t('room_price_per_night', number_format($room['price']))); ?>
                        </div>
                    </div>

                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="text-xl font-bold text-[#0d1b10] dark:text-white mb-2 leading-tight">
                            <?php echo h($r_name); ?>
                        </h3>

                        <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-4">
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">person</span>
                                <span class="font-medium"><?php echo h($r_capacity); ?></span>
                            </div>
                            <!-- Bed info is not in DB explicitly as text, using Type Name -->
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">bed</span>
                                <span class="font-medium"><?php echo h(($current_lang === 'en' && !empty($room['type_name_en'])) ? $room['type_name_en'] : $room['type_name']); ?></span>
                            </div>
                        </div>

                        <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-3 mb-6 flex-grow leading-relaxed">
                            <?php echo h($r_desc); ?>
                        </p>

                        <div class="mt-auto grid grid-cols-2 gap-3">
                            <a href="room_detail.php?id=<?php echo h($room['id']); ?>" class="flex items-center justify-center py-3 rounded-xl border border-[#cfe7d3] dark:border-[#2a452e] text-[#0d1b10] dark:text-white font-bold text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <?php echo h(t('btn_view_details')); ?>
                            </a>
                            <a href="room_detail.php?id=<?php echo h($room['id']); ?>" class="flex items-center justify-center py-3 rounded-xl bg-primary text-[#0d1b10] font-bold text-sm hover:opacity-90 transition-opacity shadow-lg shadow-primary/20">
                                <?php echo h(t('btn_proceed_to_booking') ?? 'Reserve'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- End Card Template -->
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="flex flex-col items-center justify-center p-12 bg-gray-50 dark:bg-[#1a301d] rounded-2xl border border-gray-100 dark:border-[#223a26]">
            <span class="material-symbols-outlined text-4xl text-gray-400 mb-4">sentiment_dissatisfied</span>
            <p class="text-gray-500 font-medium"><?php echo h(t('rooms_none_available')); ?></p>
        </div>
    <?php endif; ?>

</main>

<?php
require_once 'includes/footer.php';
?>
