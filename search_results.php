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

<main class="max-w-[1280px] mx-auto w-full px-4 lg:px-10 py-12">

    <!-- Search Criteria Header -->
    <div class="bg-gray-50 dark:bg-[#1a301d] border border-gray-100 dark:border-[#223a26] rounded-xl p-6 mb-8 flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-[#0d1b10] dark:text-white mb-2"><?php echo h(t('search_results_title')); ?></h2>
            <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">calendar_month</span>
                    <span class="font-semibold"><?php echo h($check_in_date); ?></span>
                    <span class="text-xs text-gray-400">to</span>
                    <span class="font-semibold"><?php echo h($check_out_date); ?></span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">group</span>
                    <span class="font-semibold"><?php echo h(t('room_capacity_people', $num_guests)); ?></span>
                </div>
            </div>
        </div>
        <a href="rooms.php?check_in_date=<?php echo h($check_in_date); ?>&check_out_date=<?php echo h($check_out_date); ?>&num_guests=<?php echo h($num_guests); ?>" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-lg text-sm font-bold text-[#4c9a59] hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
            <span class="material-symbols-outlined text-[18px]">edit</span>
            <?php echo h(t('btn_fix_search')); ?>
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/20 rounded-xl p-6 mb-8">
            <div class="flex items-center gap-3 text-red-600 dark:text-red-400 font-bold mb-2">
                <span class="material-symbols-outlined">error</span>
                Error
            </div>
            <?php foreach ($errors as $error): ?>
                <p class="text-sm text-red-500 dark:text-red-300 ml-9"><?php echo h($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php elseif (!empty($available_rooms)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($available_rooms as $room): ?>
                <?php
                    // 言語対応
                    $r_name = ($current_lang === 'en' && !empty($room['name_en'])) ? $room['name_en'] : $room['name'];
                    $r_desc = ($current_lang === 'en' && !empty($room['description_en'])) ? $room['description_en'] : $room['description'];
                    $r_capacity = t('room_capacity_people', $room['capacity']);
                    $r_image = !empty($room['main_image']) ? $room['main_image'] : 'https://via.placeholder.com/800x600?text=No+Image';

                    // Book Link with params
                    $book_link = "book.php?id=" . h($room['id']) . "&check_in=" . h($check_in_date) . "&check_out=" . h($check_out_date) . "&num_guests=" . h($num_guests);
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
                            <a href="<?php echo $book_link; ?>" class="flex items-center justify-center py-3 rounded-xl bg-primary text-[#0d1b10] font-bold text-sm hover:opacity-90 transition-opacity shadow-lg shadow-primary/20">
                                <?php echo h(t('btn_book_this_room') ?? 'Book Now'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- End Card Template -->
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="flex flex-col items-center justify-center p-16 bg-gray-50 dark:bg-[#1a301d] rounded-2xl border border-gray-100 dark:border-[#223a26]">
            <span class="material-symbols-outlined text-5xl text-gray-300 dark:text-gray-600 mb-4">search_off</span>
            <h3 class="text-lg font-bold text-gray-700 dark:text-gray-300 mb-2"><?php echo h(t('search_results_none')); ?></h3>
            <p class="text-sm text-gray-500 mb-6">Try adjusting your dates or number of guests.</p>
            <a href="javascript:history.back()" class="px-6 py-3 bg-white dark:bg-[#102213] border border-gray-200 dark:border-[#2a452e] rounded-xl font-bold text-[#4c9a59] text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                <?php echo h(t('btn_fix_search')); ?>
            </a>
        </div>
    <?php endif; ?>

</main>

<?php
require_once 'includes/footer.php';
?>
