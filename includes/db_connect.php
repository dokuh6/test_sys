<?php

// 注意: 以下の情報は、ご自身のデータベース環境に合わせて修正してください。
define('DB_HOST', 'localhost'); // データベースのホスト名
define('DB_USER', 'your_db_user');      // データベースのユーザー名
define('DB_PASS', 'your_db_password');  // データベースのパスワード
define('DB_NAME', 'guesthouse_marusho'); // データベース名

try {
    // データベースに接続
    $dbh = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    // エラー発生時に例外をスローするように設定
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // 接続エラー時のメッセージ
    // 安全のため、詳細なエラーメッセージはユーザーに表示せず、ログに記録する
    error_log('Database connection failed: ' . $e->getMessage());
    echo 'ただいまシステムメンテナンス中です。しばらくしてから再度アクセスしてください。';
    exit();
}
