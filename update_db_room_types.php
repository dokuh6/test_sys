<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    $sql_file = 'db_update_room_types_images.sql';
    if (!file_exists($sql_file)) {
        die("SQL file not found: $sql_file");
    }

    $sql = file_get_contents($sql_file);
    $dbh->exec($sql);

    echo "Database updated successfully.\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
