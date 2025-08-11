<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ゲストハウス丸正</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1><a href="index.php">ゲストハウス丸正</a></h1>
        <nav>
            <ul>
                <li><a href="rooms.php">お部屋</a></li>
                <li><a href="search.php">空室検索</a></li>
                <?php if (isset($_SESSION['user'])): ?>
                    <li>ようこそ、<?php echo h($_SESSION['user']['name']); ?>様</li>
                    <li><a href="mypage.php">マイページ</a></li>
                    <li><a href="logout.php">ログアウト</a></li>
                <?php else: ?>
                    <li><a href="login.php">ログイン</a></li>
                    <li><a href="register.php">会員登録</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
