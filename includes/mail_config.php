<?php
// SMTP設定
// 注意: 実際の運用では環境変数やセキュアな設定管理を使用すること
define('SMTP_HOST', 'smtp.example.com'); // ホスト名
define('SMTP_PORT', 587);                // ポート (587 or 465)
define('SMTP_USER', 'user@example.com'); // ユーザー名
define('SMTP_PASS', 'password');         // パスワード
define('SMTP_SECURE', 'tls');            // 暗号化 (tls or ssl)
define('FROM_EMAIL', 'noreply@guesthouse-marusho.com');
define('FROM_NAME', 'Guesthouse Marusho');
