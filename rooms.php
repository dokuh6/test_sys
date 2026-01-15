<?php
// db_connect and functions are loaded via language.php in header
require_once 'includes/header.php';

try {
    $room_type_id = filter_input(INPUT_GET, 'room_type_id', FILTER_VALIDATE_INT);

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
            JOIN room_types AS rt ON r.room_type_id = rt.id";

    // 部屋タイプID指定がある場合
    $params = [];
    if ($room_type_id) {
        $sql .= " WHERE r.room_type_id = :room_type_id";
        $params[':room_type_id'] = $room_type_id;
    }

    $sql .= " ORDER BY r.price ASC";

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "エラー: " . h($e->getMessage());
    $rooms = [];
}
?>

<div class="max-w-6xl mx-auto my-8 px-4">
    <!-- Search Section -->
    <section class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-8 border border-gray-100 dark:border-gray-700 mb-12 shadow-sm">
        <h3 class="text-xl font-bold text-center mb-6 text-gray-800 dark:text-white flex items-center justify-center gap-2">
            <span class="material-icons text-primary dark:text-blue-400">search</span>
            <?php echo h(t('index_search_title')); ?>
        </h3>
        <form action="search_results.php" method="GET" class="flex flex-col md:flex-row gap-6 justify-center items-end">
            <div class="w-full md:w-auto flex-1">
                <label for="check_in_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_check_in')); ?>:</label>
                <input type="date" id="check_in_date" name="check_in_date" value="<?php echo isset($_GET['check_in_date']) ? h($_GET['check_in_date']) : ''; ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]">
            </div>
            <div class="w-full md:w-auto flex-1">
                <label for="check_out_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_check_out')); ?>:</label>
                <input type="date" id="check_out_date" name="check_out_date" value="<?php echo isset($_GET['check_out_date']) ? h($_GET['check_out_date']) : ''; ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]">
            </div>
            <div class="w-full md:w-40">
                <label for="num_guests" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('form_num_guests')); ?>:</label>
                <input type="number" id="num_guests" name="num_guests" min="1" value="<?php echo isset($_GET['num_guests']) ? h($_GET['num_guests']) : '1'; ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 px-3 min-h-[44px]">
            </div>
            <div class="w-full md:w-auto">
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-md shadow transition-colors duration-200 flex items-center justify-center gap-2 min-h-[44px]">
                    <span class="material-icons text-sm">search</span>
                    <?php echo h(t('btn_search_again')); ?>
                </button>
            </div>
        </form>
    </section>

    <div class="flex items-center justify-between mb-8">
        <h2 class="text-3xl font-bold text-gray-800 dark:text-white border-l-4 border-primary pl-4"><?php echo h(t('rooms_list_title')); ?></h2>
        <?php if ($room_type_id): ?>
            <a href="rooms.php" class="text-primary hover:underline flex items-center gap-1">
                <span class="material-icons text-sm">undo</span>
                すべての部屋を表示
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($rooms)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            <?php foreach ($rooms as $room): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col group">
                    <div class="relative overflow-hidden h-56 bg-gray-200">
                        <?php if (!empty($room['main_image'])): ?>
                            <img src="<?php echo h($room['main_image']); ?>" alt="<?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?>" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                            <div class="flex items-center justify-center h-full text-gray-400">
                                <span class="flex flex-col items-center">
                                    <span class="material-icons text-4xl mb-2">image_not_supported</span>
                                    <?php echo h(t('no_image_available')); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 md:p-6 flex flex-col flex-grow">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?></h3>
                            <span class="bg-blue-100 text-primary text-xs font-bold px-2 py-1 rounded"><?php echo h($current_lang === 'en' && !empty($room['type_name_en']) ? $room['type_name_en'] : $room['type_name']); ?></span>
                        </div>
                        <p class="text-2xl font-bold text-red-500 mb-4"><?php echo h(t('room_price_per_night', number_format($room['price']))); ?></p>
                        <p class="text-gray-600 dark:text-gray-300 mb-4 line-clamp-3 text-sm flex-grow">
                            <?php echo h($current_lang === 'en' && !empty($room['description_en']) ? $room['description_en'] : $room['description']); ?>
                        </p>
                        <ul class="text-sm text-gray-500 dark:text-gray-400 mb-6 space-y-1">
                             <li class="flex items-center gap-2">
                                <span class="material-icons text-base">people</span>
                                <strong><?php echo h(t('room_capacity')); ?>:</strong> <?php echo h(t('room_capacity_people', $room['capacity'])); ?>
                            </li>
                        </ul>
                        <a href="room_detail.php?id=<?php echo h($room['id']); ?>" class="w-full py-2.5 px-4 rounded bg-primary text-white hover:bg-primary-dark font-semibold transition-all duration-200 flex items-center justify-center gap-2">
                            <?php echo h(t('btn_view_details')); ?>
                            <span class="material-icons text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-gray-500 dark:text-gray-400 py-12 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
            <?php echo h(t('rooms_none_available')); ?>
        </p>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
