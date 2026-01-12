<?php
$root_path = '../';
require_once $root_path . 'includes/header.php';

// 部屋情報の取得
$check_in = filter_input(INPUT_GET, 'check_in_date');
$check_out = filter_input(INPUT_GET, 'check_out_date');
$num_guests = filter_input(INPUT_GET, 'num_guests', FILTER_VALIDATE_INT);

try {
    // 部屋一覧の取得
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

    // 検索条件がある場合
    if ($check_in && $check_out && $num_guests) {
         // 簡単なキャパシティチェックのみ（本来は空室チェックも必要だがここでは簡略化、または検索結果ページへ飛ばす）
         // 検索結果ページへ飛ばすのが自然だが、ここでは一覧表示
         // なので、WHERE句でフィルタ
         $sql .= " WHERE rt.capacity >= :num_guests";
    }

    $sql .= " ORDER BY r.price ASC";

    $stmt = $dbh->prepare($sql);
    if ($check_in && $check_out && $num_guests) {
        $stmt->bindParam(':num_guests', $num_guests, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $rooms = [];
    error_log("Failed to fetch rooms: " . $e->getMessage());
}
?>

<style>
.search-container {
    background-color: #e9ecef;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 30px;
}
.search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    justify-content: center;
}
.room-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
}
.room-card {
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    width: 300px;
    overflow: hidden;
    background: #fff;
    display: flex;
    flex-direction: column;
}
.room-image {
    width: 100%;
    height: 200px;
    background-color: #eee;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #aaa;
}
.room-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.room-info {
    padding: 15px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}
.room-info h3 {
    margin-top: 0;
    font-size: 1.4rem;
    color: #004080;
}
.room-price {
    font-size: 1.2rem;
    font-weight: bold;
    color: #d9534f;
    margin-bottom: 10px;
}
.room-details ul {
    list-style: none;
    padding: 0;
    margin: 10px 0;
}
.room-details li {
    margin-bottom: 5px;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    background-color: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    text-align: center;
    margin-top: auto;
}
.btn:hover {
    background-color: #0056b3;
}
</style>

<section class="search-container">
    <h2><?php echo h(t('search_title')); ?></h2>
    <form action="<?php echo $root_path; ?>booking/search_results.php" method="GET" class="search-form">
        <div>
            <label for="check_in_date"><?php echo h(t('form_check_in')); ?>:</label><br>
            <input type="date" id="check_in_date" name="check_in_date" value="<?php echo isset($_GET['check_in_date']) ? h($_GET['check_in_date']) : ''; ?>" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label for="check_out_date"><?php echo h(t('form_check_out')); ?>:</label><br>
            <input type="date" id="check_out_date" name="check_out_date" value="<?php echo isset($_GET['check_out_date']) ? h($_GET['check_out_date']) : ''; ?>" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label for="num_guests"><?php echo h(t('form_num_guests')); ?>:</label><br>
            <input type="number" id="num_guests" name="num_guests" min="1" value="<?php echo isset($_GET['num_guests']) ? h($_GET['num_guests']) : '1'; ?>" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 60px;">
        </div>
        <div style="align-self: flex-end;">
            <button type="submit" class="btn"><?php echo h(t('btn_search_again')); ?></button>
        </div>
    </form>
</section>

<h2><?php echo h(t('rooms_list_title')); ?></h2>

<?php if (!empty($rooms)): ?>
    <div class="room-list">
        <?php foreach ($rooms as $room): ?>
            <div class="room-card">
                <div class="room-image">
                    <?php if (!empty($room['main_image'])): ?>
                        <img src="<?php echo h($root_path . $room['main_image']); ?>" alt="<?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?>">
                    <?php else: ?>
                        <span><?php echo h(t('no_image_available')); ?></span>
                    <?php endif; ?>
                </div>
                <div class="room-info">
                    <h3><?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?></h3>
                    <p class="room-price"><?php echo h(t('room_price_per_night', number_format($room['price']))); ?></p>
                    <p><?php echo h($current_lang === 'en' && !empty($room['description_en']) ? $room['description_en'] : $room['description']); ?></p>
                    <ul class="room-details">
                        <li><strong><?php echo h(t('room_type')); ?>:</strong> <?php echo h($current_lang === 'en' && !empty($room['type_name_en']) ? $room['type_name_en'] : $room['type_name']); ?></li>
                        <li><strong><?php echo h(t('room_capacity')); ?>:</strong> <?php echo h(t('room_capacity_people', $room['capacity'])); ?></li>
                    </ul>
                    <a href="room_detail.php?id=<?php echo h($room['id']); ?>" class="btn"><?php echo h(t('btn_view_details')); ?></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p><?php echo h(t('rooms_none_available')); ?></p>
<?php endif; ?>

<?php
require_once $root_path . 'includes/footer.php';
?>
