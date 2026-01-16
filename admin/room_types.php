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
    $name_en = filter_input(INPUT_POST, 'name_en');
    $description = filter_input(INPUT_POST, 'description');
    $description_en = filter_input(INPUT_POST, 'description_en');
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);

    if ($name && $capacity) {
        try {
            $sql = "INSERT INTO room_types (name, name_en, description, description_en, capacity) VALUES (:name, :name_en, :description, :description_en, :capacity)";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':name_en' => $name_en,
                ':description' => $description,
                ':description_en' => $description_en,
                ':capacity' => $capacity
            ]);
            $message = "新しい部屋タイプ「" . h($name) . "」を追加しました。";
        } catch (PDOException $e) {
            $error = "追加に失敗しました: " . h($e->getMessage());
        }
    } else {
        $error = "部屋タイプ名と収容人数は必須です。";
    }
}

// 削除処理
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id_to_delete) {
        try {
            // TODO: この部屋タイプを使用している部屋がないかチェックするべき
            $sql = "DELETE FROM room_types WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':id', $id_to_delete, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $message = "部屋タイプを削除しました。";
            } else {
                $error = "削除に失敗しました。";
            }
        } catch (PDOException $e) {
            $error = "削除に失敗しました。この部屋タイプを使用している部屋が存在する可能性があります。";
        }
    }
}


// --- データ表示 (Read) ---
try {
    $stmt = $dbh->query("SELECT * FROM room_types ORDER BY id ASC");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "部屋タイプ情報の取得に失敗しました: " . h($e->getMessage());
    $room_types = [];
}

require_once 'admin_header.php';
$csrf_token = generate_csrf_token();
?>

<style>
.add-form { background-color: #fff; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.add-form h3 { margin-top: 0; }
.form-row { display: flex; gap: 10px; margin-bottom: 10px; }
.form-row label { flex-basis: 100px; }
.form-row input, .form-row textarea { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
</style>

<h2><?php echo h(t('admin_room_types')); ?></h2>

<?php if ($message): ?><p style="color: green;"><?php echo h($message); ?></p><?php endif; ?>
<?php if ($error): ?><p style="color: red;"><?php echo h($error); ?></p><?php endif; ?>

<div class="add-form">
    <h3><?php echo h(t('admin_room_types_add')); ?></h3>
    <form action="room_types.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <label for="name"><?php echo h(t('admin_room_type_name_jp')); ?>:</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-row">
            <label for="name_en"><?php echo h(t('admin_room_type_name_en')); ?>:</label>
            <input type="text" id="name_en" name="name_en">
        </div>
        <div class="form-row">
            <label for="capacity"><?php echo h(t('admin_capacity')); ?>:</label>
            <input type="number" id="capacity" name="capacity" min="1" required>
        </div>
        <div class="form-row">
            <label for="description"><?php echo h(t('admin_desc_jp')); ?>:</label>
            <textarea id="description" name="description" rows="3"></textarea>
        </div>
        <div class="form-row">
            <label for="description_en"><?php echo h(t('admin_desc_en')); ?>:</label>
            <textarea id="description_en" name="description_en" rows="3"></textarea>
        </div>
        <button type="submit" class="btn-admin" style="background-color: #3498db;"><?php echo h(t('admin_add')); ?></button>
    </form>
</div>


<h3><?php echo h(t('admin_room_types_list')); ?></h3>
<div class="table-responsive">
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>画像</th>
            <th><?php echo h(t('admin_room_type_name_jp')); ?></th>
            <th><?php echo h(t('admin_room_type_name_en')); ?></th>
            <th><?php echo h(t('admin_visible')); ?></th>
            <th><?php echo h(t('admin_capacity')); ?></th>
            <th><?php echo h(t('admin_operation')); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($room_types as $type): ?>
            <tr>
                <td><?php echo h($type['id']); ?></td>
                <td>
                    <?php if (!empty($type['main_image'])): ?>
                        <img src="../assets/<?php echo h($type['main_image']); ?>" alt="Image" style="width: 50px; height: 40px; object-fit: cover;">
                    <?php else: ?>
                        <span style="color: #ccc;"><?php echo h(t('admin_no_image')); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo h($type['name']); ?></td>
                <td><?php echo h($type['name_en']); ?></td>
                <td>
                    <?php if (!empty($type['is_visible'])): ?>
                        <span style="color: green;"><?php echo h(t('admin_public_on')); ?></span>
                    <?php else: ?>
                        <span style="color: gray;"><?php echo h(t('admin_public_off')); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo h($type['capacity']); ?></td>
                <td>
                    <a href="edit_room_type.php?id=<?php echo h($type['id']); ?>"><?php echo h(t('admin_edit')); ?></a> |
                    <a href="room_types.php?action=delete&id=<?php echo h($type['id']); ?>" onclick="return confirm('<?php echo h(t('admin_delete_fail_constraint')); ?>');"><?php echo h(t('admin_delete')); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php
require_once 'admin_footer.php';
?>
