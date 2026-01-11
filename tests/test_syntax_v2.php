<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Checking syntax...\n";

try {
    require_once 'includes/mail_config.php';
    echo "mail_config.php loaded.\n";

    // We can't fully load functions.php because it might require db connection or other things not present in this simple test,
    // but the `require_once` at the top of functions.php loads the PHPMailer classes, so that's a good check.
    // However, functions.php itself doesn't execute code on load except for the requires.
    // Let's try to include it.
    // Note: It might fail if it relies on `includes/header.php` or similar if the path is wrong, but here functions.php seems to only require mail_config and PHPMailer.

    // Check if files exist
    $files = [
        'includes/phpmailer/src/Exception.php',
        'includes/phpmailer/src/PHPMailer.php',
        'includes/phpmailer/src/SMTP.php'
    ];

    foreach ($files as $f) {
        if (!file_exists($f)) {
            echo "ERROR: File not found: $f\n";
            exit(1);
        }
    }
    echo "PHPMailer files found.\n";

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Syntax check passed (simulated).\n";
