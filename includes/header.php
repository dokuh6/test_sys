<?php
// Central initialization (handles session, DB, language)
require_once __DIR__ . '/init.php';

// Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Default root path if not set
if (!isset($root_path)) {
    $root_path = '';
}
?>
<!DOCTYPE html>
<html lang="<?php echo h($current_lang); ?>">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo h(t('site_title')); ?> - Guesthouse Marusho</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#0f4c81", // Classic Blue
              "primary-dark": "#0a355c",
              "background-light": "#f4f4f5", // Zinc 100
              "background-dark": "#18181b", // Zinc 900
              "surface-light": "#ffffff",
              "surface-dark": "#27272a", // Zinc 800
              "text-light": "#333333",
              "text-dark": "#e4e4e7", // Zinc 200
            },
            fontFamily: {
              display: ['"Noto Sans JP"', "sans-serif"],
              sans: ['"Noto Sans JP"', "sans-serif"],
            },
            borderRadius: {
              DEFAULT: "0.5rem",
            },
          },
        },
      };
    </script>
    <style>
        body {
            font-family: 'Noto Sans JP', sans-serif;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-200 min-h-screen flex flex-col">
    <div class="bg-gray-800 dark:bg-black text-gray-300 text-sm py-2 px-4">
        <div class="max-w-6xl mx-auto flex justify-end items-center gap-4">
            <a class="hover:text-white transition-colors" href="?lang=ja">日本語</a>
            <span class="text-gray-500">|</span>
            <a class="hover:text-white transition-colors" href="?lang=en">English</a>
        </div>
    </div>
    <header class="bg-primary shadow-lg sticky top-0 z-50">
        <div class="max-w-6xl mx-auto py-4 px-4 flex flex-col md:flex-row justify-between items-center relative">

            <!-- Mobile Top Bar: Logo + Hamburger -->
            <div class="w-full md:w-auto flex justify-between items-center">
                <h1 class="text-2xl md:text-3xl font-bold text-white tracking-wider">
                    <a href="<?php echo $root_path; ?>index.php" class="hover:text-white"><?php echo h(t('site_title')); ?></a>
                </h1>
                <button id="mobile-menu-btn" class="md:hidden text-white focus:outline-none p-2">
                    <span class="material-icons text-3xl">menu</span>
                </button>
            </div>

            <!-- Desktop Nav -->
            <nav class="hidden md:flex gap-6 text-white/90 font-medium text-sm">
                <a class="hover:text-white hover:underline decoration-2 underline-offset-4 transition-all" href="<?php echo $root_path; ?>rooms.php"><?php echo h(t('nav_rooms')); ?></a>
                <a class="hover:text-white hover:underline decoration-2 underline-offset-4 transition-all" href="<?php echo $root_path; ?>check_booking.php"><?php echo h(t('nav_check_booking')); ?></a>
                <?php if (isset($_SESSION['user'])): ?>
                    <a class="hover:text-white hover:underline decoration-2 underline-offset-4 transition-all" href="<?php echo $root_path; ?>mypage.php"><?php echo h(t('nav_mypage')); ?></a>
                    <a class="hover:text-white hover:underline decoration-2 underline-offset-4 transition-all" href="<?php echo $root_path; ?>logout.php"><?php echo h(t('nav_logout')); ?></a>
                <?php else: ?>
                    <a class="hover:text-white hover:underline decoration-2 underline-offset-4 transition-all" href="<?php echo $root_path; ?>login.php"><?php echo h(t('nav_login')); ?></a>
                    <a class="hover:text-white hover:underline decoration-2 underline-offset-4 transition-all" href="<?php echo $root_path; ?>register.php"><?php echo h(t('nav_register')); ?></a>
                <?php endif; ?>
            </nav>

            <!-- Mobile Nav (Hidden by default) -->
            <div id="mobile-menu" class="hidden w-full md:hidden mt-4 border-t border-primary-dark pt-4">
                <nav class="flex flex-col gap-4 text-white font-medium text-center">
                    <a class="block py-2 hover:bg-primary-dark rounded transition-colors" href="<?php echo $root_path; ?>rooms.php"><?php echo h(t('nav_rooms')); ?></a>
                    <a class="block py-2 hover:bg-primary-dark rounded transition-colors" href="<?php echo $root_path; ?>check_booking.php"><?php echo h(t('nav_check_booking')); ?></a>
                    <?php if (isset($_SESSION['user'])): ?>
                        <a class="block py-2 hover:bg-primary-dark rounded transition-colors" href="<?php echo $root_path; ?>mypage.php"><?php echo h(t('nav_mypage')); ?></a>
                        <a class="block py-2 hover:bg-primary-dark rounded transition-colors" href="<?php echo $root_path; ?>logout.php"><?php echo h(t('nav_logout')); ?></a>
                    <?php else: ?>
                        <a class="block py-2 hover:bg-primary-dark rounded transition-colors" href="<?php echo $root_path; ?>login.php"><?php echo h(t('nav_login')); ?></a>
                        <a class="block py-2 hover:bg-primary-dark rounded transition-colors" href="<?php echo $root_path; ?>register.php"><?php echo h(t('nav_register')); ?></a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('mobile-menu-btn');
            const menu = document.getElementById('mobile-menu');
            if(btn && menu) {
                btn.addEventListener('click', function() {
                    menu.classList.toggle('hidden');
                });
            }
        });
    </script>
    <main class="flex-grow py-12 px-4">
