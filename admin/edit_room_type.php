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
    $name_en = filter_input(INPUT_POST, 'name_en');
    $description = filter_input(INPUT_POST, 'description');
    $description_en = filter_input(INPUT_POST, 'description_en');
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $is_visible = filter_input(INPUT_POST, 'is_visible', FILTER_VALIDATE_INT);
    if ($is_visible === null) $is_visible = 0;

    // 画像アップロード処理
    $image_path = null;
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/room_types/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $tmp_name = $_FILES['main_image']['tmp_name'];
        $name_file = basename($_FILES['main_image']['name']);
        $ext = strtolower(pathinfo($name_file, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed_exts)) {
            $new_filename = 'room_type_' . $id . '_' . time() . '.' . $ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                $image_path = 'images/room_types/' . $new_filename;
            } else {
                $error = "画像の保存に失敗しました。";
            }
        } else {
            $error = "許可されていないファイル形式です。";
        }
    }

    if ($name && $capacity && !$error) {
        try {
            $sql = "UPDATE room_types SET name = :name, name_en = :name_en, description = :description, description_en = :description_en, capacity = :capacity, is_visible = :is_visible";
            $params = [
                ':name' => $name,
                ':name_en' => $name_en,
                ':description' => $description,
                ':description_en' => $description_en,
                ':capacity' => $capacity,
                ':is_visible' => $is_visible,
                ':id' => $id
            ];

            if ($image_path) {
                $sql .= ", main_image = :main_image";
                $params[':main_image'] = $image_path;
            }

            $sql .= " WHERE id = :id";

            $stmt = $dbh->prepare($sql);
            $stmt->execute($params);
            $_SESSION['message'] = "部屋タイプID: " . h($id) . " の情報を更新しました。";
            header('Location: room_types.php');
            exit();
        } catch (PDOException $e) {
            $error = "更新に失敗しました: " . h($e->getMessage());
        }
    } elseif (!$error) {
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
    <form action="edit_room_type.php?id=<?php echo h($id); ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <div class="form-row">
            <label for="name">タイプ名 (日本語):</label>
            <input type="text" id="name" name="name" value="<?php echo h($room_type['name']); ?>" required>
        </div>
        <div class="form-row">
            <label for="name_en">タイプ名 (English):</label>
            <input type="text" id="name_en" name="name_en" value="<?php echo h($room_type['name_en']); ?>">
        </div>
        <div class="form-row">
            <label for="capacity">収容人数:</label>
            <input type="number" id="capacity" name="capacity" min="1" value="<?php echo h($room_type['capacity']); ?>" required>
        </div>
        <div class="form-row">
            <label>トップページ表示:</label>
            <div style="flex: 1; padding: 8px;">
                <label style="font-weight: normal;"><input type="radio" name="is_visible" value="1" <?php echo (isset($room_type['is_visible']) && $room_type['is_visible']) ? 'checked' : ''; ?>> 表示する</label>
                <label style="font-weight: normal; margin-left: 15px;"><input type="radio" name="is_visible" value="0" <?php echo (!isset($room_type['is_visible']) || !$room_type['is_visible']) ? 'checked' : ''; ?>> 表示しない</label>
            </div>
        </div>
        <div class="form-row">
            <label for="main_image">代表画像:</label>
            <div style="flex: 1;">
                <?php if (!empty($room_type['main_image'])): ?>
                    <div style="margin-bottom: 5px;">
                        <img src="../<?php echo h($room_type['main_image']); ?>" alt="現在登録されている画像" style="max-width: 200px; max-height: 150px; object-fit: cover;">
                        <br>
                        <small>現在登録されている画像</small>
                    </div>
                <?php endif; ?>
                <input type="file" id="main_image" name="main_image" accept="image/*">
                <p style="margin-top: 5px; font-size: 0.9em; color: #666;">※新しい画像を選択すると上書きされます。</p>
            </div>
        </div>
        <div class="form-row">
            <label for="description">説明 (日本語):</label>
            <textarea id="description" name="description" rows="3"><?php echo h($room_type['description']); ?></textarea>
        </div>
        <div class="form-row">
            <label for="description_en">説明 (English):</label>
            <textarea id="description_en" name="description_en" rows="3"><?php echo h($room_type['description_en']); ?></textarea>
        </div>
        <button type="submit" class="btn-admin" style="background-color: #2980b9;">更新する</button>
        <a href="room_types.php" style="margin-left: 10px;">キャンセル</a>
    </form>
</div>

<?php
require_once 'admin_footer.php';
?>
