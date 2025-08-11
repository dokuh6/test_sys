<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// 1. 入力値の取得と検証
$check_in_date = filter_input(INPUT_GET, 'check_in_date');
$check_out_date = filter_input(INPUT_GET, 'check_out_date');
$num_guests = filter_input(INPUT_GET, 'num_guests', FILTER_VALIDATE_INT);

$errors = [];
if (empty($check_in_date) || empty($check_out_date) || empty($num_guests)) {
    $errors[] = "すべての日付と人数を入力してください。";
} else {
    if (strtotime($check_in_date) >= strtotime($check_out_date)) {
        $errors[] = "チェックアウト日はチェックイン日より後の日付を選択してください。";
    }
    if (strtotime($check_in_date) < time()) {
        $errors[] = "チェックイン日は本日以降の日付を選択してください。";
    }
    if ($num_guests <= 0) {
        $errors[] = "人数は1名以上を選択してください。";
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
                            r.name AS room_name,
                            r.price,
                            rt.name AS type_name,
                            rt.capacity,
                            rt.description
                          FROM rooms AS r
                          JOIN room_types AS rt ON r.room_type_id = rt.id
                          WHERE rt.capacity >= :num_guests";

        if (!empty($booked_room_ids)) {
            // 予約済みの部屋を除外する
            $placeholders = implode(',', array_fill(0, count($booked_room_ids), '?'));
            $sql_available .= " AND r.id NOT IN ($placeholders)";
        }
        $sql_available .= " ORDER BY r.price ASC";

        $stmt_available = $dbh->prepare($sql_available);
        $stmt_available->bindParam(':num_guests', $num_guests, PDO::PARAM_INT);

        if (!empty($booked_room_ids)) {
            foreach ($booked_room_ids as $k => $id) {
                $stmt_available->bindValue(($k + 1), $id, PDO::PARAM_INT);
            }
        }

        $stmt_available->execute();
        $available_rooms = $stmt_available->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $errors[] = "データベースエラー: " . h($e->getMessage());
    }
}

require_once 'includes/header.php';
?>

<!-- rooms.phpからスタイルを拝借 -->
<style>
.room-list { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
.room-card { border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); width: 300px; overflow: hidden; background: #fff; }
.room-image { width: 100%; height: 200px; background-color: #eee; display: flex; align-items: center; justify-content: center; color: #aaa; }
.room-info { padding: 15px; }
.room-info h3 { margin-top: 0; font-size: 1.4rem; color: #004080; }
.room-price { font-size: 1.2rem; font-weight: bold; color: #d9534f; margin-bottom: 10px; }
.room-details ul { list-style: none; padding: 0; margin: 10px 0; }
.room-details li { margin-bottom: 5px; }
</style>

<div class="search_results_header" style="margin-bottom: 30px;">
    <h2>空室検索結果</h2>
    <p><strong>検索条件:</strong>
        チェックイン: <?php echo h($check_in_date); ?> |
        チェックアウト: <?php echo h($check_out_date); ?> |
        人数: <?php echo h($num_guests); ?>名様
    </p>
</div>


<?php if (!empty($errors)): ?>
    <div class="errors" style="color: red; border: 1px solid red; padding: 15px; margin-bottom: 20px;">
        <?php foreach ($errors as $error): ?>
            <p><?php echo h($error); ?></p>
        <?php endforeach; ?>
    </div>
    <p><a href="javascript:history.back()" class="btn">検索条件を修正する</a></p>
<?php elseif (!empty($available_rooms)): ?>
    <div class="room-list">
        <?php foreach ($available_rooms as $room): ?>
            <div class="room-card">
                <div class="room-image"><span>部屋の画像</span></div>
                <div class="room-info">
                    <h3><?php echo h($room['room_name']); ?></h3>
                    <p class="room-price">¥<?php echo h(number_format($room['price'])); ?> / 泊</p>
                    <p><?php echo h($room['description']); ?></p>
                    <ul class="room-details">
                        <li><strong>タイプ:</strong> <?php echo h($room['type_name']); ?></li>
                        <li><strong>定員:</strong> <?php echo h($room['capacity']); ?>名様</li>
                    </ul>
                    <a href="book.php?id=<?php echo h($room['id']); ?>&check_in=<?php echo h($check_in_date); ?>&check_out=<?php echo h($check_out_date); ?>&num_guests=<?php echo h($num_guests);?>" class="btn">この部屋を予約する</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>申し訳ございませんが、ご指定の条件に合う空室はございませんでした。</p>
    <p><a href="javascript:history.back()" class="btn">検索条件を変更する</a></p>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
