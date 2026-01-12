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
    echo "<main class='max-w-[1280px] mx-auto w-full px-4 lg:px-10 py-6'>";
    echo "<h2 class='text-2xl font-bold mb-4'>" . h(t('room_detail_not_found')) . "</h2>";
    echo "<p><a href='rooms.php' class='text-primary font-bold hover:underline'>" . h(t('btn_back_to_rooms')) . "</a></p>";
    echo "</main>";
    require_once 'includes/footer.php';
    exit();
}

// データ準備
$room_name = ($current_lang === 'en' && !empty($room['name_en'])) ? $room['name_en'] : $room['name'];
$room_desc = ($current_lang === 'en' && !empty($room['description_en'])) ? $room['description_en'] : $room['description'];
if (empty($room_desc)) {
    $room_desc = ($current_lang === 'en' && !empty($room['type_description_en'])) ? $room['type_description_en'] : $room['description'];
}
$room_price = number_format($room['price']);
$room_capacity = $room['capacity'];

// 画像プレースホルダー
$placeholder = "https://via.placeholder.com/800x600?text=No+Image";
$display_main_image = $main_image ? $main_image : $placeholder;
// サブ画像が足りない場合の埋め合わせロジックは表示時に調整

// 設備リスト (DBにないためモック)
$amenities_list = [
    ['icon' => 'wifi', 'text' => 'Ultra-fast Wi-Fi'],
    ['icon' => 'ac_unit', 'text' => 'Climate Control'],
    ['icon' => 'flatware', 'text' => 'In-room Dining'],
    ['icon' => 'pool', 'text' => 'Pool Access'],
    ['icon' => 'local_bar', 'text' => 'Mini Bar'],
    ['icon' => 'fitness_center', 'text' => 'Gym Access']
];
?>

<main class="max-w-[1280px] mx-auto w-full px-4 lg:px-10 py-6">
<!-- Breadcrumbs -->
<div class="flex flex-wrap gap-2 py-4">
<a class="text-[#4c9a59] text-sm font-medium hover:underline" href="index.php">Home</a>
<span class="text-[#4c9a59] text-sm font-medium">/</span>
<a class="text-[#4c9a59] text-sm font-medium hover:underline" href="rooms.php">Rooms</a>
<span class="text-[#4c9a59] text-sm font-medium">/</span>
<span class="text-[#0d1b10] dark:text-gray-400 text-sm font-medium"><?php echo h($room_name); ?></span>
</div>
<div class="flex flex-col gap-2 mb-6">
<h1 class="text-[#0d1b10] dark:text-white tracking-tight text-3xl lg:text-4xl font-extrabold leading-tight"><?php echo h($room_name); ?></h1>
<div class="flex items-center gap-4 text-sm font-medium">
<div class="flex items-center text-yellow-500">
<span class="material-symbols-outlined text-lg fill-1">star</span>
<span class="ml-1 text-[#0d1b10] dark:text-white">4.9 (124 reviews)</span>
</div>
<span class="text-gray-400">•</span>
<span class="text-[#4c9a59]">Guesthouse Marusho</span>
</div>
</div>
<!-- Image Gallery Grid -->
<div class="grid grid-cols-1 md:grid-cols-4 grid-rows-2 gap-3 h-[400px] md:h-[550px] mb-8">
    <!-- Main Image -->
    <div class="md:col-span-2 md:row-span-2 bg-center bg-no-repeat bg-cover rounded-xl relative group overflow-hidden" data-alt="<?php echo h($room_name); ?>" style='background-image: url("<?php echo h($display_main_image); ?>");'>
    <div class="absolute inset-0 bg-black/10 group-hover:bg-black/0 transition-colors pointer-events-none"></div>
    </div>

    <!-- Sub Images -->
    <?php
    // 最大4枚まで表示
    $display_subs = array_slice($sub_images, 0, 4);
    // 足りない分をプレースホルダーで埋める（あるいは表示しない）
    // ここでは単純にあるだけ表示し、足りない場合はグリッドが空くのを防ぐためプレースホルダーをセット
    $needed = 4 - count($display_subs);
    for ($i = 0; $i < $needed; $i++) {
        $display_subs[] = $placeholder;
    }

    foreach ($display_subs as $index => $img_path):
        // 最後の画像に「View All」オーバーレイを乗せる場合などのロジックは省略
    ?>
    <div class="hidden md:block bg-center bg-no-repeat bg-cover rounded-xl" style='background-image: url("<?php echo h($img_path); ?>");'></div>
    <?php endforeach; ?>
</div>

<!-- Content Area with Sticky Sidebar -->
<div class="flex flex-col lg:flex-row gap-12">
<!-- Left Column: Details -->
<div class="flex-1">
<!-- Tabs -->
<div class="mb-8 overflow-x-auto">
<div class="flex border-b border-[#cfe7d3] dark:border-[#223a26] gap-8 min-w-max">
<a class="flex flex-col items-center justify-center border-b-[3px] border-primary text-[#0d1b10] dark:text-white pb-3 pt-4" href="#">
<p class="text-sm font-bold leading-normal">Overview</p>
</a>
<a class="flex flex-col items-center justify-center border-b-[3px] border-transparent text-[#4c9a59] pb-3 pt-4 hover:text-[#0d1b10] dark:hover:text-white transition-colors" href="#">
<p class="text-sm font-bold leading-normal"><?php echo h(t('nav_amenities') ?? 'Amenities'); ?></p>
</a>
<a class="flex flex-col items-center justify-center border-b-[3px] border-transparent text-[#4c9a59] pb-3 pt-4 hover:text-[#0d1b10] dark:hover:text-white transition-colors" href="#availability-section">
<p class="text-sm font-bold leading-normal"><?php echo h(t('nav_availability') ?? 'Availability'); ?></p>
</a>
</div>
</div>
<!-- Room Description -->
<div class="space-y-6">
<div class="flex items-center justify-between pb-6 border-b border-gray-100 dark:border-[#223a26]">
<div class="flex gap-6">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-gray-500">bed</span>
<span class="text-sm font-medium"><?php echo h($room['type_name']); ?></span>
</div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-gray-500">person</span>
<span class="text-sm font-medium"><?php echo h(t('room_capacity_people', $room_capacity)); ?></span>
</div>
</div>
</div>
<div class="prose dark:prose-invert max-w-none">
<h3 class="text-xl font-bold mb-4">About this room</h3>
<p class="text-gray-600 dark:text-gray-400 leading-relaxed">
    <?php echo nl2br(h($room_desc)); ?>
</p>
</div>
<!-- Amenities Grid -->
<div class="pt-8">
<h3 class="text-xl font-bold mb-6">Top Amenities</h3>
<div class="grid grid-cols-2 sm:grid-cols-3 gap-6">
<?php foreach ($amenities_list as $amenity): ?>
<div class="flex items-center gap-3">
<div class="size-10 rounded-lg bg-background-light dark:bg-[#1a301d] flex items-center justify-center text-primary">
<span class="material-symbols-outlined"><?php echo h($amenity['icon']); ?></span>
</div>
<span class="font-medium text-sm"><?php echo h($amenity['text']); ?></span>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>
<!-- Right Column: Booking Widget (Sticky) -->
<div class="lg:w-[400px]">
<div class="sticky top-24 bg-white dark:bg-[#1a301d] p-6 rounded-2xl shadow-xl border border-gray-100 dark:border-[#223a26]">
<div class="flex items-baseline justify-between mb-6">
<div>
<span class="text-3xl font-extrabold">¥<?php echo h($room_price); ?></span>
<span class="text-gray-500 text-sm font-medium"> / night</span>
</div>
<div class="flex items-center text-sm font-bold text-primary">
<span class="material-symbols-outlined text-sm mr-1">bolt</span>
                                Instant Book
                            </div>
</div>

<!-- Booking Form -->
<form action="book.php" method="GET" class="space-y-4">
<input type="hidden" name="id" value="<?php echo h($room['id']); ?>">

<div class="grid grid-cols-2 border border-gray-200 dark:border-[#2a452e] rounded-xl overflow-hidden">
<div class="p-3 border-r border-gray-200 dark:border-[#2a452e]">
<label class="block text-[10px] font-bold uppercase text-gray-400 mb-1"><?php echo h(t('form_check_in') ?? 'Check-in'); ?></label>
<input class="w-full bg-transparent border-none p-0 text-sm font-semibold focus:ring-0" type="date" name="check_in" required value="<?php echo date('Y-m-d'); ?>"/>
</div>
<div class="p-3">
<label class="block text-[10px] font-bold uppercase text-gray-400 mb-1"><?php echo h(t('form_check_out') ?? 'Check-out'); ?></label>
<input class="w-full bg-transparent border-none p-0 text-sm font-semibold focus:ring-0" type="date" name="check_out" required value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"/>
</div>
<div class="col-span-2 p-3 border-t border-gray-200 dark:border-[#2a452e]">
<label class="block text-[10px] font-bold uppercase text-gray-400 mb-1"><?php echo h(t('form_num_guests') ?? 'Guests'); ?></label>
<select class="w-full bg-transparent border-none p-0 text-sm font-semibold focus:ring-0 appearance-none" name="num_guests">
<?php for($i=1; $i <= $room_capacity; $i++): ?>
<option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo ($i==1) ? 'Guest' : 'Guests'; ?></option>
<?php endfor; ?>
</select>
</div>
</div>
<button type="submit" class="w-full bg-primary hover:bg-opacity-90 text-[#0d1b10] py-4 rounded-xl font-bold text-lg transition-all shadow-lg shadow-primary/20">
    <?php echo h(t('btn_proceed_to_booking') ?? 'Reserve Now'); ?>
</button>
</form>

<div class="bg-green-50 dark:bg-primary/5 p-4 rounded-xl flex items-start gap-3 mt-6">
<span class="material-symbols-outlined text-primary text-xl">verified_user</span>
<div>
<p class="text-xs font-bold text-green-800 dark:text-primary">Free Cancellation</p>
<p class="text-[10px] text-green-700/70 dark:text-primary/70">Cancel up to 48 hours before check-in for a full refund.</p>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- Availability Calendar Section -->
<section id="availability-section" class="mt-16 py-12 border-t border-gray-100 dark:border-[#223a26]">
<h3 class="text-2xl font-bold mb-8"><?php echo h(t('availability_calendar_title') ?? 'Availability'); ?></h3>
<div class="bg-white dark:bg-[#1a301d] rounded-2xl p-8 border border-gray-100 dark:border-[#223a26]">
<!-- FullCalendar Container -->
<div id='calendar'></div>
</div>
</section>
</main>

<!-- FullCalendar -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: '<?php echo $current_lang ?? "ja"; ?>',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth'
            },
            events: 'api/get_public_availability.php?room_id=<?php echo $room_id; ?>',
            height: 'auto',
            businessHours: true,
            validRange: {
                start: '<?php echo date("Y-m-d"); ?>'
            }
        });
        calendar.render();
    });
</script>

<?php
require_once 'includes/footer.php';
?>
