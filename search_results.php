<?php
require_once 'includes/header.php';

// 1. 入力値の取得と検証
$check_in_date = filter_input(INPUT_GET, 'check_in_date');
$check_out_date = filter_input(INPUT_GET, 'check_out_date');
$num_guests = filter_input(INPUT_GET, 'num_guests', FILTER_VALIDATE_INT);

$errors = [];
if (empty($check_in_date) || empty($check_out_date) || empty($num_guests)) {
    $errors[] = t('error_all_fields_required');
} else {
    if (strtotime($check_in_date) >= strtotime($check_out_date)) {
        $errors[] = t('error_checkout_after_checkin');
    }
    // チェックイン日は今日以降であること (時刻を無視して日付のみ比較)
    $today = new DateTime('today');
    $check_in_dt = new DateTime($check_in_date);
    if ($check_in_dt < $today) {
        $errors[] = t('error_checkin_not_in_past');
    }
    if ($num_guests <= 0) {
        $errors[] = t('error_guests_positive');
    }
}

$available_rooms = [];
if (empty($errors)) {
    try {
        // 2. 指定期間に予約されている部屋IDのリストを取得
        $sql_booked = "SELECT DISTINCT br.room_id
                       FROM bookings b
                       JOIN booking_rooms br ON b.id = br.booking_id
                       WHERE b.status = 'confirmed'
                       AND (b.check_in_date < :check_out_date AND b.check_out_date > :check_in_date)";

        $stmt_booked = $dbh->prepare($sql_booked);
        $stmt_booked->bindParam(':check_in_date', $check_in_date, PDO::PARAM_STR);
        $stmt_booked->bindParam(':check_out_date', $check_out_date, PDO::PARAM_STR);
        $stmt_booked->execute();
        $booked_room_ids = $stmt_booked->fetchAll(PDO::FETCH_COLUMN, 0);

        // 3. 利用可能な部屋を検索
        $sql_available = "SELECT
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
                          WHERE rt.capacity >= :num_guests";

        if (!empty($booked_room_ids)) {
            // 予約済みの部屋を除外する
            $exclude_placeholders = [];
            foreach ($booked_room_ids as $k => $id) {
                $exclude_placeholders[] = ":exclude_id_" . $k;
            }
            $sql_available .= " AND r.id NOT IN (" . implode(',', $exclude_placeholders) . ")";
        }
        $sql_available .= " ORDER BY r.price ASC";

        $stmt_available = $dbh->prepare($sql_available);
        $stmt_available->bindParam(':num_guests', $num_guests, PDO::PARAM_INT);

        if (!empty($booked_room_ids)) {
            foreach ($booked_room_ids as $k => $id) {
                $stmt_available->bindValue(":exclude_id_" . $k, $id, PDO::PARAM_INT);
            }
        }

        $stmt_available->execute();
        $available_rooms = $stmt_available->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $errors[] = t('error_db');
    }
}
?>

<div class="max-w-6xl mx-auto my-8 px-4">
    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 border border-gray-100 dark:border-gray-700 mb-8 shadow-sm text-center">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('search_results_title')); ?></h2>
        <p class="text-gray-600 dark:text-gray-300">
            <strong><?php echo h(t('search_results_condition')); ?>:</strong>
            <?php echo h(t('form_check_in')); ?>: <?php echo h($check_in_date); ?> |
            <?php echo h(t('form_check_out')); ?>: <?php echo h($check_out_date); ?> |
            <?php echo h(t('form_num_guests')); ?>: <?php echo h(t('room_capacity_people', $num_guests)); ?>
        </p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 p-6 rounded-lg mb-8 text-center">
            <?php foreach ($errors as $error): ?>
                <p class="mb-2 last:mb-0"><?php echo h($error); ?></p>
            <?php endforeach; ?>
            <div class="mt-4">
                <a href="javascript:history.back()" class="inline-block bg-white border border-red-300 text-red-600 hover:bg-red-50 font-bold py-2 px-6 rounded shadow transition-colors duration-200">
                    <?php echo h(t('btn_fix_search')); ?>
                </a>
            </div>
        </div>
    <?php elseif (!empty($available_rooms)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($available_rooms as $room): ?>
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
                    <div class="p-6 flex flex-col flex-grow">
                        <div class="flex justify-between items-start mb-2">
                             <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?></h3>
                             <span class="bg-blue-100 text-primary text-xs font-bold px-2 py-1 rounded"><?php echo h($current_lang === 'en' && !empty($room['type_name_en']) ? $room['type_name_en'] : $room['type_name']); ?></span>
                        </div>
                        <p class="text-2xl font-bold text-red-500 mb-4"><?php echo h(t('room_price_per_night', number_format($room['price']))); ?></p>
                        <p class="text-gray-600 dark:text-gray-300 mb-4 text-sm flex-grow line-clamp-3">
                            <?php echo h($current_lang === 'en' && !empty($room['description_en']) ? $room['description_en'] : $room['description']); ?>
                        </p>
                        <ul class="text-sm text-gray-500 dark:text-gray-400 mb-6 space-y-1">
                             <li class="flex items-center gap-2">
                                <span class="material-icons text-base">people</span>
                                <strong><?php echo h(t('room_capacity')); ?>:</strong> <?php echo h(t('room_capacity_people', $room['capacity'])); ?>
                            </li>
                        </ul>
                        <a href="book.php?id=<?php echo h($room['id']); ?>&check_in=<?php echo h($check_in_date); ?>&check_out=<?php echo h($check_out_date); ?>&num_guests=<?php echo h($num_guests);?>" class="w-full py-2.5 px-4 rounded bg-primary text-white hover:bg-primary-dark font-semibold transition-all duration-200 flex items-center justify-center gap-2">
                            <?php echo h(t('btn_book_this_room')); ?>
                            <span class="material-icons text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-8 rounded-lg text-center">
            <p class="text-lg mb-4"><?php echo h(t('search_results_none')); ?></p>
            <a href="javascript:history.back()" class="inline-block bg-white border border-yellow-300 text-yellow-700 hover:bg-yellow-100 font-bold py-2 px-6 rounded shadow transition-colors duration-200">
                <?php echo h(t('btn_fix_search')); ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
