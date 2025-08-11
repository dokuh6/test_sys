<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

try {
    // 部屋タイプと結合して部屋の情報を取得するSQL
    $sql = "SELECT
                r.id,
                r.name AS room_name,
                r.price,
                rt.name AS type_name,
                rt.capacity,
                rt.description
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
    <h3 style="margin-top:0;">空室を検索</h3>
    <form action="search_results.php" method="GET" style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
        <div>
            <label for="check_in_date">チェックイン日:</label><br>
            <input type="date" id="check_in_date" name="check_in_date" value="<?php echo isset($_GET['check_in_date']) ? h($_GET['check_in_date']) : ''; ?>" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label for="check_out_date">チェックアウト日:</label><br>
            <input type="date" id="check_out_date" name="check_out_date" value="<?php echo isset($_GET['check_out_date']) ? h($_GET['check_out_date']) : ''; ?>" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label for="num_guests">人数:</label><br>
            <input type="number" id="num_guests" name="num_guests" min="1" value="<?php echo isset($_GET['num_guests']) ? h($_GET['num_guests']) : '1'; ?>" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 60px;">
        </div>
        <div style="align-self: flex-end;">
            <button type="submit" class="btn">再検索</button>
        </div>
    </form>
</section>

<h2>お部屋一覧</h2>

<?php if (!empty($rooms)): ?>
    <div class="room-list">
        <?php foreach ($rooms as $room): ?>
            <div class="room-card">
                <div class="room-image">
                    <span>部屋の画像</span>
                </div>
                <div class="room-info">
                    <h3><?php echo h($room['room_name']); ?></h3>
                    <p class="room-price">¥<?php echo h(number_format($room['price'])); ?> / 泊</p>
                    <p><?php echo h($room['description']); ?></p>
                    <ul class="room-details">
                        <li><strong>タイプ:</strong> <?php echo h($room['type_name']); ?></li>
                        <li><strong>定員:</strong> <?php echo h($room['capacity']); ?>名様</li>
                    </ul>
                    <a href="room_detail.php?id=<?php echo h($room['id']); ?>" class="btn">詳細を見る</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>現在ご利用可能なお部屋はございません。</p>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
