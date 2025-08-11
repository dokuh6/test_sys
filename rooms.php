<?php
// db_connect and functions are loaded via language.php in header
require_once 'includes/header.php';

try {
    // 部屋タイプと結合して部屋の情報を取得するSQL
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
            ORDER BY r.price ASC";

    $stmt = $dbh->query($sql);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "エラー: " . h($e->getMessage());
    $rooms = [];
}
?>

<style>
/* rooms.php専用のスタイル */
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
.room-info {
    padding: 15px;
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
</style>

<section id="search-section" style="margin-bottom: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 5px; text-align: center;">
    <h3 style="margin-top:0;"><?php echo h(t('index_search_title')); ?></h3>
    <form action="search_results.php" method="GET" style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
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
                    <span>Room Image</span>
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
require_once 'includes/footer.php';
?>
