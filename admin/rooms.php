<?php
require_once 'admin_check.php';

$message = '';
$error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- データ処理 (POST: 新規追加, GET: 削除) ---

// 新規追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    validate_csrf_token();
    $name = filter_input(INPUT_POST, 'name');
    $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);

    if ($name && $room_type_id && $price) {
        try {
            $sql = "INSERT INTO rooms (name, room_type_id, price) VALUES (:name, :room_type_id, :price)";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([':name' => $name, ':room_type_id' => $room_type_id, ':price' => $price]);
            $message = "新しい部屋「" . h($name) . "」を追加しました。";
        } catch (PDOException $e) {
            $error = "追加に失敗しました: " . h($e->getMessage());
        }
    } else {
        $error = "すべての項目を正しく入力してください。";
    }
}

// 削除処理
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id_to_delete) {
        try {
            // TODO: この部屋の予約がないかチェックするべき
            $sql = "DELETE FROM rooms WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            if ($stmt->execute([':id' => $id_to_delete])) {
                $message = "部屋を削除しました。";
            } else {
                $error = "削除に失敗しました。";
            }
        } catch (PDOException $e) {
            $error = "削除に失敗しました。この部屋を含む予約が存在する可能性があります。";
        }
    }
}


// --- データ表示 (Read) ---
try {
    // 部屋タイプ取得 (フォーム用)
    $stmt_types = $dbh->query("SELECT id, name FROM room_types ORDER BY id ASC");
    $room_types_for_form = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

    // 部屋一覧取得
    $stmt_rooms = $dbh->query("SELECT r.id, r.name, r.price, rt.name as type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id ORDER BY r.id ASC");
    $rooms = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "情報取得に失敗しました: " . h($e->getMessage());
    $rooms = [];
    $room_types_for_form = [];
}

require_once 'admin_header.php';
$csrf_token = generate_csrf_token();
?>
<style>
.add-form { background-color: #fff; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.add-form h3 { margin-top: 0; }
.form-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
.form-row label { flex-basis: 100px; }
.form-row input, .form-row select { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
</style>

<h2>部屋管理</h2>

<?php if ($message): ?><p style="color: green;"><?php echo $message; ?></p><?php endif; ?>
<?php if ($error): ?><p style="color: red;"><?php echo $error; ?></p><?php endif; ?>

<div class="add-form">
    <h3>新しい部屋を追加</h3>
    <form action="rooms.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <label for="name">部屋名/番号:</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-row">
            <label for="room_type_id">部屋タイプ:</label>
            <select id="room_type_id" name="room_type_id" required>
                <option value="">選択してください</option>
                <?php foreach ($room_types_for_form as $type): ?>
                    <option value="<?php echo h($type['id']); ?>"><?php echo h($type['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="price">料金(円):</label>
            <input type="number" id="price" name="price" min="0" step="100" required>
        </div>
        <button type="submit" class="btn-admin" style="background-color: #3498db;">追加</button>
    </form>
</div>


<h3>既存の部屋一覧</h3>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>部屋名/番号</th>
            <th>部屋タイプ</th>
            <th>料金</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rooms as $room): ?>
            <tr>
                <td><?php echo h($room['id']); ?></td>
                <td><?php echo h($room['name']); ?></td>
                <td><?php echo h($room['type_name']); ?></td>
                <td>¥<?php echo h(number_format($room['price'])); ?></td>
                <td>
                    <a href="edit_room.php?id=<?php echo h($room['id']); ?>">編集</a> |
                    <a href="rooms.php?action=delete&id=<?php echo h($room['id']); ?>" onclick="return confirm('本当にこの部屋を削除しますか？');">削除</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
require_once 'admin_footer.php';
?>
