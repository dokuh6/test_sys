<?php
// Ensure language/session is loaded.
if (file_exists('includes/language.php')) {
    require_once 'includes/language.php';
} elseif (file_exists('../includes/language.php')) {
    require_once '../includes/language.php';
}
// Default title if not set
if (!isset($page_title)) {
    $page_title = t('site_title');
}
?>
<!DOCTYPE html>
<html class="light" lang="<?php echo isset($current_lang) ? $current_lang : 'en'; ?>">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?php echo h($page_title); ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#13ec37",
                        "background-light": "#f6f8f6",
                        "background-dark": "#102213",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0d1b10] dark:text-white transition-colors duration-300">
<div class="layout-container flex h-full grow flex-col">
<!-- Top Navigation Bar -->
<header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-[#e7f3e9] dark:border-[#223a26] px-10 py-3 bg-white dark:bg-[#102213] sticky top-0 z-50">
<div class="flex items-center gap-8">
<a href="index.php" class="flex items-center gap-4 text-[#0d1b10] dark:text-primary">
<div class="size-6">
<svg fill="currentColor" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
<path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill-rule="evenodd"></path>
</svg>
</div>
<h2 class="text-[#0d1b10] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">StayGreen Hotels</h2>
</a>
<nav class="hidden md:flex items-center gap-9">
<a class="text-[#0d1b10] dark:text-gray-300 text-sm font-medium hover:text-primary transition-colors" href="rooms.php"><?php echo h(t('nav_rooms')); ?></a>
<a class="text-[#0d1b10] dark:text-gray-300 text-sm font-medium hover:text-primary transition-colors" href="rooms.php"><?php echo h(t('nav_amenities') ?? 'Amenities'); ?></a>
<a class="text-[#0d1b10] dark:text-gray-300 text-sm font-medium hover:text-primary transition-colors" href="#"><?php echo h(t('nav_offers') ?? 'Offers'); ?></a>
<a class="text-[#0d1b10] dark:text-gray-300 text-sm font-medium hover:text-primary transition-colors" href="#"><?php echo h(t('nav_about') ?? 'About Us'); ?></a>
</nav>
</div>
<div class="flex flex-1 justify-end gap-4 lg:gap-8 items-center">
<label class="hidden sm:flex flex-col min-w-40 !h-10 max-w-64">
<div class="flex w-full flex-1 items-stretch rounded-lg h-full overflow-hidden">
<div class="text-[#4c9a59] flex bg-[#e7f3e9] dark:bg-[#1a301d] items-center justify-center pl-4">
<span class="material-symbols-outlined text-xl">search</span>
</div>
<input class="form-input flex w-full min-w-0 flex-1 border-none bg-[#e7f3e9] dark:bg-[#1a301d] focus:ring-0 h-full placeholder:text-[#4c9a59] px-4 pl-2 text-sm" placeholder="Search destinations" value=""/>
</div>
</label>

<?php if (isset($_SESSION['user'])): ?>
    <!-- Logged In -->
    <a href="mypage.php" class="bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10 border-2 border-primary/20" data-alt="User profile avatar" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuD9x4-RLmdmdS6mMOSZ50Kf0EKe3Y6A0pqrQQ6fDC22rF9qkd6onhHoQSgcgtTmP6NGRV3TMooov4mbStssky3HbhzNkcaT5kWMYg8eqqqMQIj01gPpQ414VZBkavOWo8MJMPJJ6EvvWtxxuQK6zyoj77ivuxXLKXFX_50MXwTeaiTc9CILduo__iKs-IAnpDlAAK106EVm_mJLZ1_8AcguOA69FOwDIcfAfgAd_PQNrTdwZEPVh7fO5F6xFNWJh4uJ1tx7Erk9loFh");' title="<?php echo h($_SESSION['user']['name']); ?>"></a>
    <a href="logout.php" class="text-sm font-medium text-gray-500 hover:text-primary"><?php echo h(t('nav_logout')); ?></a>
<?php else: ?>
    <!-- Not Logged In -->
    <a href="login.php" class="flex min-w-[100px] cursor-pointer items-center justify-center rounded-lg h-10 px-5 bg-primary text-[#0d1b10] text-sm font-bold tracking-[0.015em] hover:opacity-90 transition-opacity">
    <span><?php echo h(t('nav_login')); ?></span>
    </a>
<?php endif; ?>

</div>
</header>
