<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

try {
    $sql = file_get_contents('db_update_bookings_phone.sql');
    $dbh->exec($sql);
    echo "Database updated successfully.\n";
} catch (PDOException $e) {
    // Column might already exist
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error updating database: " . $e->getMessage() . "\n";
    }
}
