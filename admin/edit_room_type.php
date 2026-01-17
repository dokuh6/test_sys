<?php
require_once 'admin_check.php';

// Manager Only
require_permission(ROLE_MANAGER);

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
        $upload_dir = '../assets/images/room_types/';
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

            log_admin_action($dbh, $_SESSION['user']['id'], 'update_room_type', ['room_type_id' => $id]);

            $_SESSION['message'] = t('admin_update_success');
            header('Location: room_types.php');
            exit();
        } catch (PDOException $e) {
            $error = t('admin_update_fail', h($e->getMessage()));
        }
    } elseif (!$error) {
        $error = t('admin_input_error');
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
    die("Error: " . h($e->getMessage()));
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

<h2><?php echo h(t('admin_edit_room_type')); ?></h2>

<?php if ($error): ?><p style="color: red;"><?php echo $error; ?></p><?php endif; ?>

<div class="edit-form">
    <form action="edit_room_type.php?id=<?php echo h($id); ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <div class="form-row">
            <label for="name"><?php echo h(t('admin_room_type_name_jp')); ?>:</label>
            <input type="text" id="name" name="name" value="<?php echo h($room_type['name']); ?>" required>
        </div>
        <div class="form-row">
            <label for="name_en"><?php echo h(t('admin_room_type_name_en')); ?>:</label>
            <input type="text" id="name_en" name="name_en" value="<?php echo h($room_type['name_en']); ?>">
        </div>
        <div class="form-row">
            <label for="capacity"><?php echo h(t('admin_capacity')); ?>:</label>
            <input type="number" id="capacity" name="capacity" min="1" value="<?php echo h($room_type['capacity']); ?>" required>
        </div>
        <div class="form-row">
            <label><?php echo h(t('admin_visible')); ?>:</label>
            <div style="flex: 1; padding: 8px;">
                <label style="font-weight: normal;"><input type="radio" name="is_visible" value="1" <?php echo (isset($room_type['is_visible']) && $room_type['is_visible']) ? 'checked' : ''; ?>> <?php echo h(t('admin_visible_on')); ?></label>
                <label style="font-weight: normal; margin-left: 15px;"><input type="radio" name="is_visible" value="0" <?php echo (!isset($room_type['is_visible']) || !$room_type['is_visible']) ? 'checked' : ''; ?>> <?php echo h(t('admin_visible_off')); ?></label>
            </div>
        </div>
        <div class="form-row">
            <label for="main_image"><?php echo h(t('admin_image_main')); ?>:</label>
            <div style="flex: 1;">
                <?php if (!empty($room_type['main_image'])): ?>
                    <div style="margin-bottom: 5px;">
                        <img src="../assets/<?php echo h($room_type['main_image']); ?>" alt="Current Image" style="max-width: 200px; max-height: 150px; object-fit: cover;">
                        <br>
                        <small>Current Image</small>
                    </div>
                <?php endif; ?>
                <input type="file" id="main_image" name="main_image" accept="image/*">
                <p style="margin-top: 5px; font-size: 0.9em; color: #666;"><?php echo h(t('admin_image_upload')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <label for="description"><?php echo h(t('admin_desc_jp')); ?>:</label>
            <textarea id="description" name="description" rows="3"><?php echo h($room_type['description']); ?></textarea>
        </div>
        <div class="form-row">
            <label for="description_en"><?php echo h(t('admin_desc_en')); ?>:</label>
            <textarea id="description_en" name="description_en" rows="3"><?php echo h($room_type['description_en']); ?></textarea>
        </div>
        <button type="submit" class="btn-admin" style="background-color: #2980b9;"><?php echo h(t('admin_update')); ?></button>
        <a href="room_types.php" style="margin-left: 10px;"><?php echo h(t('admin_cancel')); ?></a>
    </form>
</div>

<?php
require_once 'admin_footer.php';
?>
