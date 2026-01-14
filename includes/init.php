<?php
// Central initialization file

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration
require_once __DIR__ . '/config.php';

// Load DB connection
require_once __DIR__ . '/db_connect.php';

// Load functions
require_once __DIR__ . '/functions.php';

// Load language settings (depends on session)
require_once __DIR__ . '/language.php';
?>
