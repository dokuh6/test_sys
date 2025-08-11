<?php
// 共通関数の読み込み
require_once 'includes/functions.php';
// ヘッダーの読み込み
require_once 'includes/header.php';
?>

<div style="text-align: center;">
    <h2>ようこそ、ゲストハウス丸正へ</h2>
    <p>心安らぐひとときをお過ごしください。</p>
    <p>当ゲストハウスは、静かな環境と温かいおもてなしで、お客様の旅の疲れを癒します。</p>
</div>

<section id="search-section" style="margin-top: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 5px; text-align: center;">
    <h3 style="margin-top:0;">空室を検索</h3>
    <form action="search_results.php" method="GET" style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
        <div>
            <label for="check_in_date">チェックイン日:</label><br>
            <input type="date" id="check_in_date" name="check_in_date" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label for="check_out_date">チェックアウト日:</label><br>
            <input type="date" id="check_out_date" name="check_out_date" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div>
            <label for="num_guests">人数:</label><br>
            <input type="number" id="num_guests" name="num_guests" min="1" value="1" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 60px;">
        </div>
        <div style="align-self: flex-end;">
            <button type="submit" class="btn">検索</button>
        </div>
    </form>
</section>


<section id="rooms" style="margin-top: 40px;">
    <h3>お部屋のご紹介</h3>
    <p>現在、準備中です。ご期待ください。</p>
    <!-- 今後、ここに部屋の一覧が表示されます -->
</section>

<?php
// フッターの読み込み
require_once 'includes/footer.php';
?>
