<?php
require_once '../includes/db_connect.php';

try {
    echo "Starting database update...<br>";

    // Check if column exists
    $check_sql = "SHOW COLUMNS FROM bookings LIKE 'booking_number'";
    $stmt = $dbh->prepare($check_sql);
    $stmt->execute();

    if ($stmt->fetch()) {
        echo "Column 'booking_number' already exists.<br>";
    } else {
        // Add the column
        $sql = "ALTER TABLE bookings ADD COLUMN booking_number VARCHAR(20) DEFAULT NULL AFTER id";
        $dbh->exec($sql);
        echo "Column 'booking_number' added successfully.<br>";

        // Add index for searching
        $sql_index = "CREATE INDEX idx_booking_number ON bookings(booking_number)";
        $dbh->exec($sql_index);
        echo "Index on 'booking_number' added successfully.<br>";
    }

    echo "Database update completed.";

} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
