<?php
// メール送信設定 (Gmail用テンプレート)

// SMTPサーバーの設定
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // TLSの場合は587, SSLの場合は465
define('SMTP_ENCRYPTION', 'tls'); // 'tls' または 'ssl'

// 認証情報 (※環境に合わせて書き換えてください)
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');

// 送信元情報
define('MAIL_FROM_ADDRESS', 'noreply@guesthouse-marusho.com');
define('MAIL_FROM_NAME', 'ゲストハウス丸正');
