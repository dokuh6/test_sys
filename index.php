<?php
require_once 'includes/init.php';
require_once 'includes/header.php';
?>

<div class="max-w-5xl mx-auto bg-surface-light dark:bg-surface-dark rounded-xl shadow-xl overflow-hidden transition-colors duration-200">
    <div class="py-12 px-6 text-center">
        <h2 class="text-2xl md:text-3xl font-bold mb-6 text-gray-800 dark:text-white"><?php echo h(t('index_welcome_title')); ?></h2>
        <div class="space-y-4 text-gray-600 dark:text-gray-300 leading-relaxed max-w-4xl mx-auto text-left md:text-center">
            <p><?php echo h(t('index_description')); ?></p>
        </div>
    </div>
    <div class="bg-gray-50 dark:bg-gray-800/50 mx-6 mb-12 rounded-lg p-8 border border-gray-100 dark:border-gray-700">
        <h3 class="text-xl font-bold text-center mb-8 text-gray-800 dark:text-white flex items-center justify-center gap-2">
            <span class="material-icons text-primary dark:text-blue-400">search</span>
            <?php echo h(t('index_search_title')); ?>
        </h3>

        <!-- Toggle Button for Calendar -->
        <div class="text-center mb-6">
            <button id="toggle-calendar-btn" class="text-primary dark:text-blue-400 hover:underline flex items-center justify-center gap-2 mx-auto bg-white dark:bg-gray-700 px-4 py-2 rounded shadow-sm">
                <span class="material-icons">calendar_month</span>
                <?php echo h(t('calendar_btn_toggle')); ?>
            </button>
        </div>

        <!-- Calendar Section (Hidden by default or Modal-like) -->
        <div id="search-calendar-container" class="hidden mb-8 bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner">
             <div id="search-calendar"></div>
             <p class="text-xs text-center text-gray-500 mt-2"><?php echo h(t('calendar_note_red')); ?></p>
        </div>

        <form class="flex flex-col md:flex-row gap-6 justify-center items-end" action="search_results.php" method="GET">
            <div class="w-full md:w-auto flex-1">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="check_in_date"><?php echo h(t('form_check_in')); ?>:</label>
                <div class="relative">
                    <input class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3" id="check_in_date" name="check_in_date" type="date" required />
                </div>
            </div>
            <div class="w-full md:w-auto flex-1">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="check_out_date"><?php echo h(t('form_check_out')); ?>:</label>
                <div class="relative">
                    <input class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3" id="check_out_date" name="check_out_date" type="date" required />
                </div>
            </div>
            <div class="w-full md:w-40">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="num_guests"><?php echo h(t('form_num_guests')); ?>:</label>
                <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3" id="num_guests" name="num_guests">
                    <option value="1">1名</option>
                    <option value="2">2名</option>
                    <option value="3">3名</option>
                    <option value="4">4名</option>
                    <option value="5">5名以上</option>
                </select>
            </div>
            <div class="w-full md:w-40">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" for="num_children"><?php echo h(t('form_children') ?? '人数(子供)'); ?>:</label>
                <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2.5 px-3" id="num_children" name="num_children">
                    <option value="0">0名</option>
                    <option value="1">1名</option>
                    <option value="2">2名</option>
                    <option value="3">3名</option>
                    <option value="4">4名</option>
                    <option value="5">5名</option>
                </select>
            </div>
            <div class="w-full md:w-auto">
                <button class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2.5 px-8 rounded-md shadow transition-colors duration-200 flex items-center justify-center gap-2" type="submit">
                    <span class="material-icons text-sm">search</span>
                    <?php echo h(t('btn_search')); ?>
                </button>
            </div>
        </form>
    </div>
    <div class="px-8 pb-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            <!-- Facility Info -->
            <div>
                <h3 class="text-xl font-bold mb-6 text-gray-800 dark:text-white flex items-center gap-2">
                    <span class="material-icons text-primary dark:text-blue-400">info</span>
                    <?php echo h(t('index_about_title')); ?>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-[max-content_1fr] gap-4 text-gray-600 dark:text-gray-300">
                    <div class="font-bold text-gray-800 dark:text-gray-200 whitespace-nowrap">
                        <?php echo h(t('facility_1f')); ?><span class="text-sm font-normal block sm:inline sm:ml-1 text-gray-500 dark:text-gray-400"><?php echo h(t('facility_1f_sub')); ?></span>:
                    </div>
                    <div>
                        <?php echo h(t('facility_1f_desc')); ?>
                    </div>

                    <div class="font-bold text-gray-800 dark:text-gray-200 whitespace-nowrap">
                        <?php echo h(t('facility_2f')); ?><span class="text-sm font-normal block sm:inline sm:ml-1 text-gray-500 dark:text-gray-400"><?php echo h(t('facility_2f_sub')); ?></span>:
                    </div>
                    <div>
                        <?php echo h(t('facility_2f_desc')); ?>
                    </div>

                    <div class="font-bold text-gray-800 dark:text-gray-200 whitespace-nowrap">
                        <?php echo h(t('facility_parking')); ?>:
                    </div>
                    <div>
                        <?php echo h(t('facility_parking_desc')); ?>
                    </div>
                </div>
            </div>

            <!-- Pricing Info -->
            <div>
                <h3 class="text-xl font-bold mb-6 text-gray-800 dark:text-white flex items-center gap-2">
                    <span class="material-icons text-primary dark:text-blue-400">payments</span>
                    <?php echo h(t('index_pricing_title')); ?>
                </h3>
                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 border border-gray-100 dark:border-gray-700">
                    <ul class="space-y-4 text-gray-600 dark:text-gray-300">
                        <li class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 pb-2">
                            <span><?php echo h(t('pricing_adult')); ?> <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo h(t('pricing_adult_sub')); ?></span></span>
                            <span class="font-bold text-lg text-gray-800 dark:text-white">¥<?php echo number_format(PRICE_PER_ADULT); ?></span>
                        </li>
                        <li class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 pb-2">
                            <span><?php echo h(t('pricing_child')); ?> <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo h(t('pricing_child_sub')); ?></span></span>
                            <div class="text-right">
                                <span class="font-bold text-lg text-gray-800 dark:text-white">¥<?php echo number_format(PRICE_PER_CHILD); ?></span>
                                <span class="text-xs block text-gray-500 dark:text-gray-400"><?php echo h(t('pricing_bedding_note')); ?></span>
                            </div>
                        </li>
                        <li class="text-sm pt-2 text-gray-500 dark:text-gray-400">
                            <?php echo h(t('pricing_infant_note')); ?><span class="font-bold text-primary dark:text-blue-400"><?php echo h(t('pricing_infant_free')); ?></span>
                        </li>
                        <li class="mt-4 pt-4 border-t-2 border-dashed border-gray-200 dark:border-gray-700">
                            <span class="font-bold text-gray-800 dark:text-white"><?php echo h(t('pricing_payment_label')); ?></span> <?php echo h(t('pricing_payment_text')); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="px-8 pb-16">
        <div class="border-t border-gray-200 dark:border-gray-700 pt-10">
            <h3 class="text-xl font-bold mb-8 text-gray-800 dark:text-white flex items-center gap-2">
                <span class="material-icons text-primary dark:text-blue-400">hotel</span>
                <?php echo h(t('index_rooms_intro_title')); ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                // 部屋タイプをDBから取得
                try {
                    $stmt = $dbh->query("SELECT * FROM room_types WHERE is_visible = 1 ORDER BY id ASC");
                    $display_room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // エラー時は空配列
                    $display_room_types = [];
                    // 開発環境であればエラー表示など
                }

                if (empty($display_room_types)) {
                    // データがない場合のフォールバック（または非表示）
                    echo '<p class="text-gray-600 dark:text-gray-300 col-span-3 text-center">' . h(t('index_rooms_empty')) . '</p>';
                } else {
                    foreach ($display_room_types as $type):
                        // 言語対応
                        $type_name = (!empty($type['name_en']) && isset($_SESSION['lang']) && $_SESSION['lang'] === 'en') ? $type['name_en'] : $type['name'];
                        $type_desc = (!empty($type['description_en']) && isset($_SESSION['lang']) && $_SESSION['lang'] === 'en') ? $type['description_en'] : $type['description'];
                        // 画像がない場合のデフォルト画像（プレースホルダー）
                        $img_src = !empty($type['main_image']) ? 'assets/' . h($type['main_image']) : 'https://via.placeholder.com/600x400?text=No+Image';
                ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col group">
                    <div class="relative overflow-hidden h-48">
                        <img alt="<?php echo h($type_name); ?>" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500" src="<?php echo $img_src; ?>"/>
                    </div>
                    <div class="p-6 flex flex-col flex-grow">
                        <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-2"><?php echo h($type_name); ?></h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                            <span class="material-icons text-sm align-middle mr-1">group</span>
                            <?php echo h(t('room_max_capacity', $type['capacity'])); ?>
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-6 flex-grow leading-relaxed">
                            <?php echo nl2br(h($type_desc)); ?>
                        </p>
                        <a href="rooms.php?room_type_id=<?php echo h($type['id']); ?>" class="w-full py-2.5 px-4 rounded border border-primary text-primary hover:bg-primary hover:text-white dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-400 dark:hover:text-gray-900 font-semibold transition-all duration-200 flex items-center justify-center gap-1 group-hover:gap-2">
                            <?php echo h(t('room_btn_detail')); ?>
                            <span class="material-icons text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>
                <?php endforeach; } ?>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar Script for Search -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Calendar Visibility
    const toggleBtn = document.getElementById('toggle-calendar-btn');
    const calendarContainer = document.getElementById('search-calendar-container');
    const calendarEl = document.getElementById('search-calendar');
    let calendar = null;

    toggleBtn.addEventListener('click', function() {
        calendarContainer.classList.toggle('hidden');
        if (!calendarContainer.classList.contains('hidden') && !calendar) {
            initCalendar();
        }
    });

    function initCalendar() {
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: '<?php echo $current_lang ?? "ja"; ?>',
            selectable: true,
            selectOverlap: true, // 満室の日はイベントでカバーするが、選択自体はイベントと被ってもOK（ただしロジックで弾く）
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: 'today'
            },
            height: 'auto',
            contentHeight: 'auto',
            events: 'api/get_aggregated_availability.php',
            validRange: {
                start: '<?php echo date("Y-m-d"); ?>'
            },
            selectLongPressDelay: 0, // スマホでも長押し不要で選択可能にする
            select: function(info) {
                // 選択された日付をセット
                document.getElementById('check_in_date').value = info.startStr;

                if (info.endStr) {
                    document.getElementById('check_out_date').value = info.endStr;
                } else {
                    // デフォルト1泊 (タイムゾーン問題を避けるため手動計算)
                    let date = new Date(info.start);
                    date.setDate(date.getDate() + 1);
                    let year = date.getFullYear();
                    let month = (date.getMonth() + 1).toString().padStart(2, '0');
                    let day = date.getDate().toString().padStart(2, '0');
                    document.getElementById('check_out_date').value = `${year}-${month}-${day}`;
                }

                // フォームへスクロール
                document.getElementById('check_in_date').scrollIntoView({ behavior: 'smooth', block: 'center' });
            },
            dateClick: function(info) {
                // スマホでタップしただけの時も同様の処理を行う
                document.getElementById('check_in_date').value = info.dateStr;

                // デフォルト1泊 (タイムゾーン問題を避けるため手動計算)
                let date = new Date(info.date);
                date.setDate(date.getDate() + 1);
                let year = date.getFullYear();
                let month = (date.getMonth() + 1).toString().padStart(2, '0');
                let day = date.getDate().toString().padStart(2, '0');
                document.getElementById('check_out_date').value = `${year}-${month}-${day}`;

                // フォームへスクロール
                document.getElementById('check_in_date').scrollIntoView({ behavior: 'smooth', block: 'center' });
            },
            eventClick: function(info) {
                 // 満室イベント(background)をクリックしてもselectは発火しない場合がある
                 // しかしbackgroundイベントはクリックを透過するはず。
            }
        });
        calendar.render();
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>
