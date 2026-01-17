<?php
require_once 'includes/header.php';

// 1. URLから部屋IDを取得し、検証する
$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$room_id) {
    // IDが無効な場合はトップページにリダイレクト
    header('Location: index.php');
    exit();
}

// 2. データベースから特定の部屋情報を取得する
try {
    $sql = "SELECT
                r.id,
                r.name,
                r.name_en,
                r.price,
                r.price_adult,
                r.price_child,
                rt.name AS type_name,
                rt.name_en AS type_name_en,
                rt.capacity,
                rt.description,
                rt.description_en
            FROM rooms AS r
            JOIN room_types AS rt ON r.room_type_id = rt.id
            WHERE r.id = :id";

    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':id', $room_id, PDO::PARAM_INT);
    $stmt->execute();
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    // 部屋が見つかった場合、関連画像を取得
    if ($room) {
        $stmt_images = $dbh->prepare("SELECT image_path, is_main FROM room_images WHERE room_id = :id ORDER BY is_main DESC, id ASC");
        $stmt_images->bindParam(':id', $room_id, PDO::PARAM_INT);
        $stmt_images->execute();
        $images = $stmt_images->fetchAll(PDO::FETCH_ASSOC);

        // 画像をメインとサブに分類
        $main_image = null;
        $sub_images = [];
        foreach ($images as $image) {
            if ($image['is_main']) {
                $main_image = $image['image_path'];
            } else {
                $sub_images[] = $image['image_path'];
            }
        }
    }

} catch (PDOException $e) {
    // エラーが発生した場合は、エラーメッセージを表示して終了
    die("データベースエラー: " . h($e->getMessage()));
}

// 部屋が見つからない場合
if (!$room) {
    header("HTTP/1.0 404 Not Found");
    echo "<div class='max-w-4xl mx-auto my-12 p-8 bg-surface-light dark:bg-surface-dark rounded-xl shadow-lg text-center'>";
    echo "<h2 class='text-2xl font-bold text-gray-800 dark:text-white mb-4'>" . h(t('room_detail_not_found')) . "</h2>";
    echo "<a href='rooms.php' class='inline-block bg-primary hover:bg-primary-dark text-white font-bold py-2.5 px-6 rounded-md shadow transition-colors duration-200'>" . h(t('btn_back_to_rooms')) . "</a>";
    echo "</div>";
    require_once 'includes/footer.php';
    exit();
}
?>

<div class="max-w-6xl mx-auto my-8 px-4">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Images Section (Left 2/3) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="relative overflow-hidden rounded-lg shadow-md h-96 bg-gray-200">
                <?php if ($main_image): ?>
                    <img src="assets/<?php echo h($main_image); ?>" alt="<?php echo h($room['name']); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="flex items-center justify-center h-full text-gray-400">
                        <span class="flex flex-col items-center">
                            <span class="material-icons text-5xl mb-2">image_not_supported</span>
                            <span class="text-xl"><?php echo h(t('no_image_available')); ?></span>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($sub_images)): ?>
                <div class="grid grid-cols-4 gap-4">
                    <?php foreach ($sub_images as $sub_image): ?>
                        <div class="relative overflow-hidden rounded-lg shadow-sm h-24 bg-gray-200">
                            <img src="assets/<?php echo h($sub_image); ?>" alt="Sub image for <?php echo h($room['name']); ?>" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Room Info (Below Images) -->
            <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-lg p-8 border border-gray-100 dark:border-gray-700">
                <div class="border-b-2 border-primary pb-4 mb-6">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white">
                        <?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?>
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 font-medium">
                        <?php echo h($current_lang === 'en' && !empty($room['type_name_en']) ? $room['type_name_en'] : $room['type_name']); ?>
                    </p>
                </div>

                <p class="text-gray-600 dark:text-gray-300 leading-relaxed mb-6 text-lg">
                    <?php echo nl2br(h($current_lang === 'en' && !empty($room['description_en']) ? $room['description_en'] : $room['description'])); ?>
                </p>

                <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300 mb-4">
                    <span class="material-icons text-gray-500">people</span>
                    <strong><?php echo h(t('room_capacity')); ?>:</strong> <?php echo h(t('room_capacity_people', $room['capacity'])); ?>
                </div>

                <div class="mt-8">
                     <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                        <span class="material-icons text-primary dark:text-blue-400">calendar_month</span>
                        <?php echo h(t('availability_calendar_title')); ?>
                    </h3>
                    <div id='calendar' class="bg-white dark:bg-gray-800 p-2 md:p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700 text-sm md:text-base"></div>
                    <style>
                        /* カレンダーのモバイル最適化 */
                        @media (max-width: 640px) {
                            .fc-toolbar {
                                flex-direction: column;
                                gap: 8px;
                            }
                            .fc-toolbar-title {
                                font-size: 1.25rem !important;
                            }
                            .fc .fc-button {
                                padding: 0.2rem 0.5rem;
                                font-size: 0.875rem;
                            }
                        }
                    </style>
                </div>

                <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                        <span class="material-icons text-primary dark:text-blue-400">info</span>
                        <?php echo h(t('info_guide_title')); ?>
                    </h3>

                    <div class="space-y-6">
                        <!-- Pricing -->
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('info_pricing_title')); ?></h4>
                            <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 pl-2">
                                <li><?php echo h(t('info_pricing_adult')); ?></li>
                                <li><?php echo h(t('info_pricing_child')); ?></li>
                                <li class="text-sm"><?php echo h(t('info_pricing_infant')); ?></li>
                                <li class="text-sm"><?php echo h(t('info_pricing_payment')); ?></li>
                            </ul>
                        </div>

                        <!-- Check-in/out -->
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('info_checkin_title')); ?></h4>
                            <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 pl-2">
                                <li><?php echo h(t('info_checkin_in')); ?></li>
                                <li><?php echo h(t('info_checkin_out')); ?></li>
                                <li class="text-sm"><?php echo h(t('info_checkin_note')); ?></li>
                            </ul>
                        </div>

                        <!-- Booking -->
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('info_booking_title')); ?></h4>
                            <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 pl-2">
                                <li><?php echo h(t('info_booking_1')); ?></li>
                                <li><?php echo h(t('info_booking_2')); ?></li>
                                <li><?php echo h(t('info_booking_3')); ?></li>
                                <li><?php echo h(t('info_booking_4')); ?></li>
                            </ul>
                        </div>

                        <!-- Cancellation -->
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white mb-2"><?php echo h(t('info_cancel_title')); ?></h4>
                            <p class="text-gray-600 dark:text-gray-300 mb-2"><?php echo h(t('info_cancel_desc')); ?></p>
                            <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-1 pl-2">
                                <li><?php echo h(t('info_cancel_policy_1')); ?></li>
                                <li><?php echo h(t('info_cancel_policy_2')); ?></li>
                                <li><?php echo h(t('info_cancel_policy_3')); ?></li>
                                <li class="text-sm"><?php echo h(t('info_cancel_policy_note')); ?></li>
                                <li class="text-sm"><?php echo h(t('info_cancel_policy_except')); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Sidebar (Right 1/3) -->
        <div class="lg:col-span-1">
            <div class="sticky top-6 bg-surface-light dark:bg-surface-dark rounded-xl shadow-lg p-6 border border-gray-100 dark:border-gray-700">
                <div class="text-center mb-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-1"><?php echo h(t('room_price_per_night', '')); ?></p>
                    <p class="text-3xl font-bold text-red-500">¥<?php echo h(number_format($room['price'])); ?></p>
                </div>

                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                    <?php echo h(t('room_detail_book_form_title')); ?>
                </h3>

                <form action="book.php" method="GET" class="space-y-4">
                    <input type="hidden" name="id" value="<?php echo h($room['id']); ?>">
                    <div>
                        <label for="check_in_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo h(t('form_check_in')); ?>:</label>
                        <input type="date" id="check_in_date" name="check_in" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2 px-3">
                    </div>
                    <div>
                        <label for="check_out_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo h(t('form_check_out')); ?>:</label>
                        <input type="date" id="check_out_date" name="check_out" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2 px-3">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="num_guests" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo h(t('form_num_guests') ?? '大人'); ?> (¥<?php echo number_format($room['price_adult']); ?>):</label>
                            <input type="number" id="num_guests" name="num_guests" min="1" max="<?php echo h($room['capacity']); ?>" value="1" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2 px-3">
                        </div>
                        <div>
                            <label for="num_children" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo h(t('form_children')); ?> (¥<?php echo number_format($room['price_child']); ?>):</label>
                            <input type="number" id="num_children" name="num_children" min="0" max="<?php echo h($room['capacity']); ?>" value="0" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2 px-3">
                            <p class="text-xs text-gray-500 mt-1"><?php echo h(t('children_age_note')); ?></p>
                        </div>
                    </div>


                    <!-- Dynamic Price Display -->
                    <div id="price-calculation-result" class="hidden p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo t('booking_info_nights_count', '<span id="total-nights">0</span>'); ?></span>
                            <span class="text-xs text-gray-400">× ¥<?php echo h(number_format($room['price'])); ?></span>
                        </div>
                        <div class="flex justify-between items-end border-t border-gray-200 dark:border-gray-700 pt-2 mt-2">
                            <span class="font-bold text-gray-700 dark:text-gray-200"><?php echo h(t('label_total')); ?></span>
                            <span class="text-2xl font-bold text-primary dark:text-blue-400">¥<span id="total-price">0</span></span>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-4 rounded-md shadow transition-colors duration-200 flex items-center justify-center gap-2 mt-4">
                        <?php echo h(t('btn_proceed_to_booking')); ?>
                        <span class="material-icons text-sm">arrow_forward</span>
                    </button>
                </form>
            </div>

             <div class="mt-6 text-center">
                <a href="rooms.php" class="text-primary dark:text-blue-400 hover:underline flex items-center justify-center gap-1">
                    <span class="material-icons text-sm">arrow_back</span>
                    <?php echo h(t('btn_back_to_rooms')); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var isMobile = window.innerWidth < 768;
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: isMobile ? 'listWeek' : 'dayGridMonth',
            locale: '<?php echo $current_lang ?? "ja"; ?>',
            selectable: true,
            selectOverlap: false,
            selectMirror: true,
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: 'today'
            },
            events: 'api/get_public_availability.php?room_id=<?php echo $room_id; ?>',
            height: 'auto',
            contentHeight: 'auto',
            aspectRatio: 1.35, // モバイルで見やすい比率
            businessHours: true,
            validRange: {
                start: '<?php echo date("Y-m-d"); ?>'
            },
            // モバイル用調整
            views: {
                dayGridMonth: {
                    titleFormat: { year: 'numeric', month: 'short' } // スマホでタイトルが長くなりすぎないように
                }
            },
            selectLongPressDelay: 0, // スマホでも長押し不要で選択可能にする
            windowResize: function(view) {
                if (window.innerWidth < 768) {
                    calendar.changeView('listWeek');
                } else {
                    calendar.changeView('dayGridMonth');
                }
            },
            select: function(info) {
                var checkInInput = document.getElementById('check_in_date');
                var checkOutInput = document.getElementById('check_out_date');

                checkInInput.value = info.startStr;

                if (info.endStr) {
                     checkOutInput.value = info.endStr;
                } else {
                     var date = new Date(info.start);
                     date.setDate(date.getDate() + 1);
                     var year = date.getFullYear();
                     var month = (date.getMonth() + 1).toString().padStart(2, '0');
                     var day = date.getDate().toString().padStart(2, '0');
                     checkOutInput.value = `${year}-${month}-${day}`;
                }

                checkInInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                checkInInput.focus();

                // Trigger change event to update price
                checkInInput.dispatchEvent(new Event('change'));
                checkOutInput.dispatchEvent(new Event('change'));
            },
            dateClick: function(info) {
                var checkInInput = document.getElementById('check_in_date');
                var checkOutInput = document.getElementById('check_out_date');

                checkInInput.value = info.dateStr;

                // デフォルト1泊 (タイムゾーン問題を避けるため手動計算)
                var date = new Date(info.date);
                date.setDate(date.getDate() + 1);
                var year = date.getFullYear();
                var month = (date.getMonth() + 1).toString().padStart(2, '0');
                var day = date.getDate().toString().padStart(2, '0');
                checkOutInput.value = `${year}-${month}-${day}`;

                checkInInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                checkInInput.focus();

                // Trigger change event to update price
                checkInInput.dispatchEvent(new Event('change'));
                checkOutInput.dispatchEvent(new Event('change'));
            }
        });
        calendar.render();

        // Dynamic Price Calculation Script
        // Use room specific prices, with fallback to constants just in case (though DB should have them)
        const ADULT_PRICE = <?php echo isset($room['price_adult']) ? (int)$room['price_adult'] : PRICE_PER_ADULT; ?>;
        const CHILD_PRICE = <?php echo isset($room['price_child']) ? (int)$room['price_child'] : PRICE_PER_CHILD; ?>;

        const checkInInput = document.getElementById('check_in_date');
        const checkOutInput = document.getElementById('check_out_date');
        const numGuestsInput = document.getElementById('num_guests');
        const numChildrenInput = document.getElementById('num_children');

        const resultDiv = document.getElementById('price-calculation-result');
        const totalNightsSpan = document.getElementById('total-nights');
        const totalPriceSpan = document.getElementById('total-price');

        function updatePrice() {
            const checkInVal = checkInInput.value;
            const checkOutVal = checkOutInput.value;
            const adults = parseInt(numGuestsInput.value) || 0;
            const children = parseInt(numChildrenInput.value) || 0;

            if (checkInVal && checkOutVal) {
                const checkIn = new Date(checkInVal);
                const checkOut = new Date(checkOutVal);

                if (checkOut > checkIn) {
                    const diffTime = Math.abs(checkOut - checkIn);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    const total = diffDays * (adults * ADULT_PRICE + children * CHILD_PRICE);

                    if (totalNightsSpan) totalNightsSpan.textContent = diffDays;
                    if (totalPriceSpan) totalPriceSpan.textContent = total.toLocaleString();
                    if (resultDiv) resultDiv.classList.remove('hidden');
                } else {
                    if (resultDiv) resultDiv.classList.add('hidden');
                }
            } else {
                if (resultDiv) resultDiv.classList.add('hidden');
            }
        }

        checkInInput.addEventListener('change', updatePrice);
        checkOutInput.addEventListener('change', updatePrice);
        numGuestsInput.addEventListener('change', updatePrice);
        numChildrenInput.addEventListener('change', updatePrice);
        // Also update on input for smoother experience
        numGuestsInput.addEventListener('input', updatePrice);
        numChildrenInput.addEventListener('input', updatePrice);
    });
</script>

<?php
require_once 'includes/footer.php';
?>
