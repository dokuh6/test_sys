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
    $description = filter_input(INPUT_POST, 'description');
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);

    if ($name && $capacity) {
        try {
            $sql = "INSERT INTO room_types (name, description, capacity) VALUES (:name, :description, :capacity)";
            $stmt = $dbh->prepare($sql);
            $stmt->execute([':name' => $name, ':description' => $description, ':capacity' => $capacity]);
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

<h2>部屋タイプ管理</h2>

<?php if ($message): ?><p style="color: green;"><?php echo $message; ?></p><?php endif; ?>
<?php if ($error): ?><p style="color: red;"><?php echo $error; ?></p><?php endif; ?>

<div class="add-form">
    <h3>新しい部屋タイプを追加</h3>
    <form action="room_types.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <label for="name">タイプ名:</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-row">
            <label for="capacity">収容人数:</label>
            <input type="number" id="capacity" name="capacity" min="1" required>
        </div>
        <div class="form-row">
            <label for="description">説明:</label>
            <textarea id="description" name="description" rows="3"></textarea>
        </div>
        <button type="submit" class="btn-admin" style="background-color: #3498db;">追加</button>
    </form>
</div>


<h3>既存の部屋タイプ一覧</h3>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>タイプ名</th>
            <th>説明</th>
            <th>収容人数</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($room_types as $type): ?>
            <tr>
                <td><?php echo h($type['id']); ?></td>
                <td><?php echo h($type['name']); ?></td>
                <td><?php echo h($type['description']); ?></td>
                <td><?php echo h($type['capacity']); ?></td>
                <td>
                    <a href="edit_room_type.php?id=<?php echo h($type['id']); ?>">編集</a> |
                    <a href="room_types.php?action=delete&id=<?php echo h($type['id']); ?>" onclick="return confirm('本当にこの部屋タイプを削除しますか？関連する部屋も削除される可能性があります。');">削除</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
require_once 'admin_footer.php';
?>
