<?php
require_once 'admin_check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: rooms.php');
    exit();
}

$message = '';
$error = '';

// --- 更新処理 (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $name = filter_input(INPUT_POST, 'name');
    $name_en = filter_input(INPUT_POST, 'name_en');
    $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);

    if ($name && $room_type_id && $price) {
        try {
            $sql = "UPDATE rooms SET name = :name, name_en = :name_en, room_type_id = :room_type_id, price = :price WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':name_en' => $name_en,
                ':room_type_id' => $room_type_id,
                ':price' => $price,
                ':id' => $id
            ]);
            $_SESSION['message'] = "部屋ID: " . h($id) . " の情報を更新しました。";
            header('Location: rooms.php');
            exit();
        } catch (PDOException $e) {
            $error = "更新に失敗しました: " . h($e->getMessage());
        }
    } else {
        $error = "すべての項目を正しく入力してください。";
    }
}

// --- データ表示 (GET) ---
try {
    // 部屋タイプ取得 (フォーム用)
    $stmt_types = $dbh->query("SELECT id, name FROM room_types ORDER BY id ASC");
    $room_types_for_form = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

    // 編集対象の部屋情報を取得
    $stmt_room = $dbh->prepare("SELECT * FROM rooms WHERE id = :id");
    $stmt_room->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_room->execute();
    $room = $stmt_room->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        header('Location: rooms.php');
        exit();
    }
} catch (PDOException $e) {
    die("情報の取得に失敗しました: " . h($e->getMessage()));
}

require_once 'admin_header.php';
$csrf_token = generate_csrf_token();
?>
<style>
.edit-form { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px; }
.edit-form h3 { margin-top: 0; }
.form-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
.form-row label { flex-basis: 100px; }
.form-row input, .form-row select { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
</style>

<h2>部屋の編集</h2>

<?php if ($error): ?><p style="color: red;"><?php echo $error; ?></p><?php endif; ?>

<div class="edit-form">
    <form action="edit_room.php?id=<?php echo h($id); ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <div class="form-row">
            <label for="name">部屋名/番号 (日本語):</label>
            <input type="text" id="name" name="name" value="<?php echo h($room['name']); ?>" required>
        </div>
        <div class="form-row">
            <label for="name_en">部屋名/番号 (English):</label>
            <input type="text" id="name_en" name="name_en" value="<?php echo h($room['name_en']); ?>">
        </div>
        <div class="form-row">
            <label for="room_type_id">部屋タイプ:</label>
            <select id="room_type_id" name="room_type_id" required>
                <option value="">選択してください</option>
                <?php foreach ($room_types_for_form as $type): ?>
                    <option value="<?php echo h($type['id']); ?>" <?php echo ($type['id'] == $room['room_type_id']) ? 'selected' : ''; ?>>
                        <?php echo h($type['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="price">料金(円):</label>
            <input type="number" id="price" name="price" min="0" step="100" value="<?php echo h($room['price']); ?>" required>
        </div>
        <button type="submit" class="btn-admin" style="background-color: #2980b9;">更新する</button>
        <a href="rooms.php" style="margin-left: 10px;">キャンセル</a>
    </form>
</div>

<?php
require_once 'admin_footer.php';
?>
