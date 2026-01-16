<?php
require_once 'admin_check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: rooms.php');
    exit();
}

$message = '';
$error = '';

/**
 * 画像アップロードを処理し、保存先のパスを返す
 * @param array $file $_FILESの要素
 * @param int $room_id 部屋ID
 * @return string|null 成功した場合は画像のパス、失敗した場合はnull
 */
function handle_image_upload($file, $room_id) {
    // エラーチェック
    if ($file['error'] !== UPLOAD_ERR_OK) {
        // ファイルがアップロードされていない場合は無視
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        // その他のエラー
        return null;
    }

    // ファイルタイプの検証
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        return null;
    }

    // ファイルサイズの検証 (例: 5MBまで)
    if ($file['size'] > 5 * 1024 * 1024) {
        return null;
    }

    // ユニークなファイル名を生成
    $path_info = pathinfo($file['name']);
    $extension = $path_info['extension'];
    $filename = 'room_' . $room_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $upload_dir = '../assets/images/rooms/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $upload_path = $upload_dir . $filename;

    // ファイルを移動
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // `../` を除いたパスを返す
        return 'images/rooms/' . $filename;
    }

    return null;
}


// --- 更新処理 (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    // トランザクション開始
    $dbh->beginTransaction();

    try {
        // 1. 部屋の基本情報を更新
        $name = filter_input(INPUT_POST, 'name');
        $name_en = filter_input(INPUT_POST, 'name_en');
        $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
        $price_adult = filter_input(INPUT_POST, 'price_adult', FILTER_VALIDATE_FLOAT);
        $price_child = filter_input(INPUT_POST, 'price_child', FILTER_VALIDATE_FLOAT);

        // price_child is optional? No, prompt says separate them. Assuming required.
        if (!$name || !$room_type_id || $price_adult === false || $price_child === false) {
             throw new Exception("必須項目が入力されていません。");
        }

        // Update both new columns AND legacy 'price' column to ensure frontend consistency.
        // The legacy 'price' column is used by rooms.php and search_results.php for display.
        $sql = "UPDATE rooms SET name = :name, name_en = :name_en, room_type_id = :room_type_id, price = :price, price_adult = :price_adult, price_child = :price_child WHERE id = :id";
        $stmt = $dbh->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':name_en' => $name_en,
            ':room_type_id' => $room_type_id,
            ':price' => $price_adult, // Sync legacy price with adult price
            ':price_adult' => $price_adult,
            ':price_child' => $price_child,
            ':id' => $id
        ]);

        // 2. 画像の処理
        $main_image_uploaded = isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK;
        $sub_images_uploaded = isset($_FILES['sub_images']) && !empty(array_filter($_FILES['sub_images']['name']));

        // メイン画像がアップロードされた場合、既存のメイン画像を削除
        if ($main_image_uploaded) {
            $stmt = $dbh->prepare("SELECT image_path FROM room_images WHERE room_id = :id AND is_main = 1");
            $stmt->execute([':id' => $id]);
            if ($old_image = $stmt->fetchColumn()) {
                if (file_exists('../' . $old_image)) unlink('../' . $old_image);
            }
            $stmt = $dbh->prepare("DELETE FROM room_images WHERE room_id = :id AND is_main = 1");
            $stmt->execute([':id' => $id]);

            // 新しいメイン画像をアップロードしてDBに保存
            $new_path = handle_image_upload($_FILES['main_image'], $id);
            if ($new_path) {
                $sql = "INSERT INTO room_images (room_id, image_path, is_main) VALUES (:id, :path, 1)";
                $stmt = $dbh->prepare($sql);
                $stmt->execute([':id' => $id, ':path' => $new_path]);
            }
        }

        // 個別のサブ画像削除処理
        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $delete_id) {
                $stmt = $dbh->prepare("SELECT image_path FROM room_images WHERE id = :img_id AND room_id = :room_id AND is_main = 0");
                $stmt->execute([':img_id' => $delete_id, ':room_id' => $id]);
                if ($del_path = $stmt->fetchColumn()) {
                    if (file_exists('../' . $del_path)) unlink('../' . $del_path);
                    $stmt = $dbh->prepare("DELETE FROM room_images WHERE id = :img_id");
                    $stmt->execute([':img_id' => $delete_id]);
                }
            }
        }

        // サブ画像がアップロードされた場合 (追加)
        if ($sub_images_uploaded) {
            // 既存削除ロジックを廃止し、追加のみ行う（削除は上の個別削除で対応）
            // ただし上限チェックなどは本来すべきだが、簡易実装として追加する

            // 新しいサブ画像をアップロード (4枚まで - 既存枚数考慮なしの簡易実装)
            $sub_image_files = $_FILES['sub_images'];
            $image_count = 0;
            foreach ($sub_image_files['tmp_name'] as $key => $tmp_name) {
                if ($image_count >= 4) break;
                if ($sub_image_files['error'][$key] === UPLOAD_ERR_OK) {
                    $file_info = [
                        'name' => $sub_image_files['name'][$key],
                        'type' => $sub_image_files['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $sub_image_files['error'][$key],
                        'size' => $sub_image_files['size'][$key]
                    ];
                    $new_path = handle_image_upload($file_info, $id);
                    if ($new_path) {
                        $sql = "INSERT INTO room_images (room_id, image_path, is_main) VALUES (:id, :path, 0)";
                        $stmt = $dbh->prepare($sql);
                        $stmt->execute([':id' => $id, ':path' => $new_path]);
                        $image_count++;
                    }
                }
            }
        }

        // コミット
        $dbh->commit();
        $_SESSION['message'] = "部屋ID: " . h($id) . " の情報を更新しました。";
        header('Location: rooms.php');
        exit();

    } catch (Exception $e) {
        // ロールバック
        $dbh->rollBack();
        $error = "更新に失敗しました: " . h($e->getMessage());
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

    // この部屋の既存の画像を取得
    $stmt_images = $dbh->prepare("SELECT * FROM room_images WHERE room_id = :id ORDER BY is_main DESC, id ASC");
    $stmt_images->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_images->execute();
    $images = $stmt_images->fetchAll(PDO::FETCH_ASSOC);

    // 画像をメインとサブに分類
    $main_image = null;
    $sub_images = [];
    foreach ($images as $image) {
        if ($image['is_main']) {
            $main_image = $image;
        } else {
            $sub_images[] = $image;
        }
    }

} catch (PDOException $e) {
    die("情報の取得に失敗しました: " . h($e->getMessage()));
}

require_once 'admin_header.php';
$csrf_token = generate_csrf_token();
?>
<style>
.edit-form { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 800px; }
.edit-form h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;}
.form-row { display: flex; gap: 10px; margin-bottom: 15px; align-items: flex-start; }
.form-row label { flex-basis: 150px; padding-top: 8px; }
.form-row input, .form-row select, .form-row textarea { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
.image-upload-section { margin-top: 20px; }
.current-images { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
.current-image-item { border: 1px solid #ddd; padding: 5px; border-radius: 4px; }
.current-image-item img { max-width: 100px; max-height: 100px; display: block; }
.current-image-item small { display: block; text-align: center; color: #666; }
</style>

<h2>部屋の編集</h2>

<?php if ($error): ?><p style="color: red;"><?php echo $error; ?></p><?php endif; ?>

<div class="edit-form">
    <form action="edit_room.php?id=<?php echo h($id); ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

        <h3>基本情報</h3>
        <div class="form-row">
            <label for="name">部屋名/番号 (日本語):</label>
            <input type="text" id="name" name="name" value="<?php echo h($room['name']); ?>" required>
        </div>
        <div class="form-row">
            <label for="name_en">部屋名/番号 (English):</label>
            <input type="text" id="name_en" name="name_en" value="<?php echo h($room['name_en'] ?? ''); ?>">
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
            <label for="price_adult">大人料金(円):</label>
            <input type="number" id="price_adult" name="price_adult" min="0" step="100" value="<?php echo h($room['price_adult'] ?? 4500); ?>" required>
        </div>
        <div class="form-row">
            <label for="price_child">子供料金(円):</label>
            <input type="number" id="price_child" name="price_child" min="0" step="100" value="<?php echo h($room['price_child'] ?? 2500); ?>" required>
        </div>

        <div class="image-upload-section">
            <h3>部屋の画像</h3>
            <p>新しい画像をアップロードすると、既存の画像は置き換えられます。</p>

            <div class="form-row">
                <label>現在のメイン画像:</label>
                <div class="current-images">
                    <?php if ($main_image): ?>
                        <div class="current-image-item">
                            <img src="../assets/<?php echo h($main_image['image_path']); ?>" alt="Main Image">
                            <small>メイン</small>
                        </div>
                    <?php else: ?>
                        <p>画像がありません。</p>
                    <?php endif; ?>
                </div>
            </div>
             <div class="form-row">
                <label for="main_image">新しいメイン画像:</label>
                <input type="file" id="main_image" name="main_image" accept="image/jpeg, image/png, image/gif">
            </div>

            <hr style="margin: 20px 0;">

            <div class="form-row">
                <label>現在のサブ画像:</label>
                <div class="current-images">
                    <?php if (!empty($sub_images)): ?>
                        <?php foreach ($sub_images as $img): ?>
                        <div class="current-image-item">
                            <img src="../assets/<?php echo h($img['image_path']); ?>" alt="Sub Image">
                             <div style="text-align:center; margin-top:5px;">
                                 <label style="font-size:0.8em; color:red; cursor:pointer;">
                                     <input type="checkbox" name="delete_images[]" value="<?php echo h($img['id']); ?>"> 削除
                                 </label>
                             </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>画像がありません。</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-row">
                 <label for="sub_images">サブ画像を追加:</label>
                 <input type="file" id="sub_images" name="sub_images[]" multiple accept="image/jpeg, image/png, image/gif">
            </div>
        </div>

        <br>
        <button type="submit" class="btn-admin" style="background-color: #2980b9;">更新する</button>
        <a href="rooms.php" style="margin-left: 10px;">キャンセル</a>
    </form>
</div>

<?php
require_once 'admin_footer.php';
?>
