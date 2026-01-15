<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - ゲストハウス丸正</title>
    <style>
        body { font-family: sans-serif; margin: 0; background-color: #f4f7f6; }
        .admin-header { background-color: #2c3e50; color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .admin-header h1 { margin: 0; font-size: 1.5rem; }
        .admin-header a { color: white; text-decoration: none; }
        .admin-nav a { margin-left: 1rem; }
        .admin-container { display: flex; }
        .admin-sidebar { width: 220px; background-color: #34495e; color: white; min-height: calc(100vh - 58px); padding-top: 1rem;}
        .admin-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .admin-sidebar li a { display: block; padding: 1rem; color: white; text-decoration: none; border-bottom: 1px solid #46627f; }
        .admin-sidebar li a:hover { background-color: #46627f; }
        .admin-main { flex: 1; padding: 2rem; max-width: 100%; box-sizing: border-box; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 0; background-color: white; min-width: 600px; }
        th, td { border: 1px solid #ddd; padding: 0.8rem; text-align: left; }
        th { background-color: #eaf2f5; }
        .btn-admin { padding: 8px 15px; border: none; border-radius: 4px; color: white; cursor: pointer; text-decoration: none; display: inline-block; }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .admin-container { flex-direction: column; }
            .admin-sidebar { width: 100%; min-height: auto; display: none; }
            .admin-sidebar.active { display: block; }
            .admin-sidebar ul { display: block; }
            .admin-sidebar li { text-align: left; border-bottom: 1px solid #46627f; }

            .admin-header { flex-direction: row; flex-wrap: wrap; gap: 10px; padding: 0.8rem; }
            .admin-header .logo-group { display: flex; align-items: center; gap: 10px; flex-grow: 1; }
            .admin-nav { width: 100%; text-align: right; border-top: 1px solid #46627f; padding-top: 10px; margin-top: 5px; display: none; }
            .admin-nav.active { display: block; }

            /* Toggle buttons */
            #sidebar-toggle, #nav-toggle { display: block !important; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 5px; }

            .admin-main { padding: 1rem; }
        }

        #sidebar-toggle, #nav-toggle { display: none; }
        .btn-cancel { background-color: #e74c3c; }
        .btn-cancel:hover { background-color: #c0392b; }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="logo-group">
            <button id="sidebar-toggle">☰</button>
            <h1><a href="index.php">管理画面</a></h1>
        </div>
        <button id="nav-toggle">⋮</button>
        <div class="admin-nav" id="admin-nav">
            <span>ようこそ、<?php echo h($_SESSION['user']['name']); ?>様</span>
            <a href="../logout.php">ログアウト</a>
            <a href="../index.php" target="_blank">サイトを表示</a>
        </div>
    </header>
    <div class="admin-container">
        <aside class="admin-sidebar" id="admin-sidebar">
            <ul>
                <li><a href="index.php">ダッシュボード</a></li>
                <li><a href="bookings.php">予約管理</a></li>
                <li><a href="rooms.php">部屋管理</a></li>
                <li><a href="room_types.php">部屋タイプ管理</a></li>
                <li><a href="users.php">顧客管理</a></li>
                <li><a href="calendar.php">空室カレンダー</a></li>
                <li><a href="email_logs.php">メール送信履歴</a></li>
            </ul>
        </aside>
        <main class="admin-main">
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var sidebarToggle = document.getElementById('sidebar-toggle');
                    var sidebar = document.getElementById('admin-sidebar');
                    var navToggle = document.getElementById('nav-toggle');
                    var nav = document.getElementById('admin-nav');

                    if(sidebarToggle && sidebar) {
                        sidebarToggle.addEventListener('click', function() {
                            sidebar.classList.toggle('active');
                        });
                    }

                    if(navToggle && nav) {
                        navToggle.addEventListener('click', function() {
                            nav.classList.toggle('active');
                        });
                    }
                });
            </script>
