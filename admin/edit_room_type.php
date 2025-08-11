<?php
require_once 'admin_check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: room_types.php');
    exit();
}

$message = '';
$error = '';

// --- 更新処理 (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $name = filter_input(INPUT_POST, 'name');
    $description = filter_input(INPUT_POST, 'description');
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);

    if ($name && $capacity) {
        try {
            $sql = "UPDATE room_types SET name = :name, description = :description, capacity = :capacity WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':capacity' => $capacity,
                ':id' => $id
            ]);
            $_SESSION['message'] = "部屋タイプID: " . h($id) . " の情報を更新しました。";
            header('Location: room_types.php');
            exit();
        } catch (PDOException $e) {
            $error = "更新に失敗しました: " . h($e->getMessage());
        }
    } else {
        $error = "部屋タイプ名と収容人数は必須です。";
    }
}

// --- データ表示 (GET) ---
try {
    $stmt = $dbh->prepare("SELECT * FROM room_types WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $room_type = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room_type) {
        header('Location: room_types.php');
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
.form-row { display: flex; gap: 10px; margin-bottom: 10px; }
.form-row label { flex-basis: 100px; }
.form-row input, .form-row textarea { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
</style>

<h2>部屋タイプの編集</h2>

<?php if ($error): ?><p style="color: red;"><?php echo $error; ?></p><?php endif; ?>

<div class="edit-form">
    <form action="edit_room_type.php?id=<?php echo h($id); ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <div class="form-row">
            <label for="name">タイプ名:</label>
            <input type="text" id="name" name="name" value="<?php echo h($room_type['name']); ?>" required>
        </div>
        <div class="form-row">
            <label for="capacity">収容人数:</label>
            <input type="number" id="capacity" name="capacity" min="1" value="<?php echo h($room_type['capacity']); ?>" required>
        </div>
        <div class="form-row">
            <label for="description">説明:</label>
            <textarea id="description" name="description" rows="3"><?php echo h($room_type['description']); ?></textarea>
        </div>
        <button type="submit" class="btn-admin" style="background-color: #2980b9;">更新する</button>
        <a href="room_types.php" style="margin-left: 10px;">キャンセル</a>
    </form>
</div>

<?php
require_once 'admin_footer.php';
?>
