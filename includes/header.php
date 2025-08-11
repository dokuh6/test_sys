<?php require_once 'includes/language.php'; ?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(t('site_title')); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .lang-switcher { text-align: right; padding: 0 20px 10px; background: #333; color: white; }
        .lang-switcher a { color: white; margin: 0 5px; }
    </style>
</head>
<body>
    <header>
        <div class="lang-switcher">
            <a href="?lang=ja">日本語</a> | <a href="?lang=en">English</a>
        </div>
        <h1><a href="index.php"><?php echo h(t('site_title')); ?></a></h1>
        <nav>
            <ul>
                <li><a href="rooms.php"><?php echo h(t('nav_rooms')); ?></a></li>
                <li><a href="search.php"><?php echo h(t('nav_availability_search')); ?></a></li>
                <?php if (isset($_SESSION['user'])): ?>
                    <li><?php echo h(t('nav_welcome', $_SESSION['user']['name'])); ?></li>
                    <li><a href="mypage.php"><?php echo h(t('nav_mypage')); ?></a></li>
                    <li><a href="logout.php"><?php echo h(t('nav_logout')); ?></a></li>
                <?php else: ?>
                    <li><a href="login.php"><?php echo h(t('nav_login')); ?></a></li>
                    <li><a href="register.php"><?php echo h(t('nav_register')); ?></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
