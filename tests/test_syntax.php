<?php
// Simple syntax check for modified files
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'includes/functions.php';
    echo "includes/functions.php loaded successfully.\n";
} catch (Exception $e) {
    echo "Error loading includes/functions.php: " . $e->getMessage() . "\n";
}

try {
    // book.php requires valid post data or get data to run meaningfully,
    // but we can at least checking if it compiles.
    // However, book.php has side effects (redirects, database writes) on load if post data is present.
    // So we just check syntax via lint.
} catch (Exception $e) {
}
