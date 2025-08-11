<?php
// 共通関数の読み込みは header.php 内の language.php で行われる
// ヘッダーの読み込み
require_once 'includes/header.php';
?>

<div style="text-align: center;">
    <h2><?php echo h(t('index_welcome_title')); ?></h2>
    <p><?php echo h(t('index_welcome_subtitle')); ?></p>
    <p><?php echo h(t('index_welcome_text')); ?></p>
</div>

<section id="search-section" style="margin-top: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 5px; text-align: center;">
    <h3 style="margin-top:0;"><?php echo h(t('index_search_title')); ?></h3>
    <form action="search_results.php" method="GET" style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
        <div>
            <label for="check_in_date"><?php echo h(t('form_check_in')); ?>:</label><br>
            <input type="date" id="check_in_date" name="check_in_date" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label for="check_out_date"><?php echo h(t('form_check_out')); ?>:</label><br>
            <input type="date" id="check_out_date" name="check_out_date" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label for="num_guests"><?php echo h(t('form_num_guests')); ?>:</label><br>
            <input type="number" id="num_guests" name="num_guests" min="1" value="1" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 60px;">
        </div>
        <div style="align-self: flex-end;">
            <button type="submit" class="btn"><?php echo h(t('btn_search')); ?></button>
        </div>
    </form>
</section>


<section id="rooms" style="margin-top: 40px;">
    <h3><?php echo h(t('index_rooms_title')); ?></h3>
    <p><?php echo h(t('index_rooms_text')); ?></p>
    <!-- 今後、ここに部屋の一覧が表示されます -->
</section>

<?php
// フッターの読み込み
require_once 'includes/footer.php';
?>
