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
    // 404 Not Found的なメッセージを表示
    header("HTTP/1.0 404 Not Found");
    echo "<h2>" . h(t('room_detail_not_found')) . "</h2>";
    echo "<p><a href='rooms.php' class='btn'>" . h(t('btn_back_to_rooms')) . "</a></p>";
    require_once 'includes/footer.php';
    exit();
}
?>
<style>
.room-detail-container {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}
.room-images-section {
    flex: 1;
    min-width: 350px;
}
.main-image-container {
    width: 100%;
    height: 400px;
    background-color: #eee;
    border-radius: 5px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.main-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.sub-images-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-top: 10px;
}
.sub-image-item {
    width: 100%;
    padding-top: 100%; /* 1:1 Aspect Ratio */
    position: relative;
    background-color: #eee;
    border-radius: 4px;
    overflow: hidden;
}
.sub-image-item img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.room-detail-info {
    flex: 1;
    min-width: 300px;
}
.room-detail-info h2 {
    margin-top: 0;
    border-bottom: 2px solid #004080;
    padding-bottom: 10px;
}
.room-price {
    font-size: 1.8rem;
    font-weight: bold;
    color: #d9534f;
    margin: 20px 0;
}
.booking-form-section {
    margin-top: 30px;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 5px;
}
</style>

<div class="room-detail-container">
    <div class="room-images-section">
        <div class="main-image-container">
            <?php if ($main_image): ?>
                <img src="<?php echo h($main_image); ?>" alt="<?php echo h($room['name']); ?>">
            <?php else: ?>
                <span style="color: #aaa; font-size: 1.5rem;"><?php echo h(t('no_image_available')); ?></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($sub_images)): ?>
            <div class="sub-images-container">
                <?php foreach ($sub_images as $sub_image): ?>
                    <div class="sub-image-item">
                        <img src="<?php echo h($sub_image); ?>" alt="Sub image for <?php echo h($room['name']); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="room-detail-info">
        <h2>
            <?php echo h($current_lang === 'en' && !empty($room['name_en']) ? $room['name_en'] : $room['name']); ?>
            (<?php echo h($current_lang === 'en' && !empty($room['type_name_en']) ? $room['type_name_en'] : $room['type_name']); ?>)
        </h2>
        <p><?php echo nl2br(h($current_lang === 'en' && !empty($room['description_en']) ? $room['description_en'] : $room['description'])); ?></p>
        <ul>
            <li><strong><?php echo h(t('room_capacity')); ?>:</strong> <?php echo h(t('room_capacity_people', $room['capacity'])); ?></li>
        </ul>
        <p class="room-price"><?php echo h(t('room_price_per_night', number_format($room['price']))); ?></p>

        <div class="booking-form-section">
            <h3><?php echo h(t('room_detail_book_form_title')); ?></h3>
            <p><?php echo h(t('room_detail_book_form_text')); ?></p>
            <form action="book.php" method="GET">
                <input type="hidden" name="id" value="<?php echo h($room['id']); ?>">
                <div>
                    <label for="check_in_date"><?php echo h(t('form_check_in')); ?>:</label>
                    <input type="date" id="check_in_date" name="check_in" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <br>
                <div>
                    <label for="check_out_date"><?php echo h(t('form_check_out')); ?>:</label>
                    <input type="date" id="check_out_date" name="check_out" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <br>
                <div>
                    <label for="num_guests"><?php echo h(t('form_num_guests')); ?>:</label>
                    <input type="number" id="num_guests" name="num_guests" min="1" max="<?php echo h($room['capacity']); ?>" value="1" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 80px;">
                </div>
                <br>
                <button type="submit" class="btn"><?php echo h(t('btn_proceed_to_booking')); ?></button>
            </form>
        </div>

    </div>
</div>
<br>
<a href="rooms.php" class="btn"><?php echo h(t('btn_back_to_rooms')); ?></a>

<?php
require_once 'includes/footer.php';
?>
