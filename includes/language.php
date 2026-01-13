<?php
// Note: This file should be included via includes/init.php which handles session_start()

// 設定定数 (Ensure this is defined if not already)
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', 'admin@example.com');
}

// 1. 言語の決定ロジック
$available_langs = ['ja', 'en'];
$default_lang = 'ja';

// URLパラメータで言語が指定された場合
if (isset($_GET['lang']) && in_array($_GET['lang'], $available_langs)) {
    $current_lang = $_GET['lang'];
    $_SESSION['lang'] = $current_lang;
}
// セッションに言語情報がある場合
elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $available_langs)) {
    $current_lang = $_SESSION['lang'];
}
// ブラウザの言語設定を確認する場合 (簡易的)
elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    if (in_array($browser_lang, $available_langs)) {
        $current_lang = $browser_lang;
    } else {
        $current_lang = $default_lang;
    }
}
// デフォルト言語
else {
    $current_lang = $default_lang;
}

// 2. 言語ファイルの読み込み
$lang_file_path = __DIR__ . '/../lang/' . $current_lang . '.json';

if (file_exists($lang_file_path)) {
    $lang_text = json_decode(file_get_contents($lang_file_path), true);
} else {
    // フォールバックとしてデフォルト言語を読み込む
    $lang_text = json_decode(file_get_contents(__DIR__ . '/../lang/' . $default_lang . '.json'), true);
}

// 3. 翻訳ヘルパー関数の定義
// この関数はグローバルスコープで定義されるため、どこからでも呼び出せる
// 引数を可変長にして、sprintfに対応
if (!function_exists('t')) {
    function t($key, ...$args) {
        global $lang_text;
        $string = isset($lang_text[$key]) ? $lang_text[$key] : $key;
        if (!empty($args)) {
            return vsprintf($string, $args);
        }
        return $string;
    }
}
?>
