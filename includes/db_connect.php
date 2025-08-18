<?php

// 注意: 以下の情報は、ご自身のデータベース環境に合わせて修正してください。
define('DB_HOST', 'localhost'); // データベースのホスト名
define('DB_USER', 'your_db_user');      // データベースのユーザー名
define('DB_PASS', 'your_db_password');  // データベースのパスワード
define('DB_NAME', 'guesthouse_marusho'); // データベース名

try {
    // データベースに接続
    $dbh = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
    // エラー発生時に例外をスローするように設定
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // 接続エラー時のメッセージ
    echo 'データベース接続に失敗しました。: ' . $e->getMessage();
    exit();
}
