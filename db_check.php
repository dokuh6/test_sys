<?php
require_once 'includes/init.php';
try {
     = $dbh->query("SELECT image_path FROM room_images LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Room Images:\n";
    print_r($rows);
    $stmt = $dbh->query("SELECT main_image FROM room_types LIMIT 5");
    $rows2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Room Types:\n";
    print_r($rows2);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
