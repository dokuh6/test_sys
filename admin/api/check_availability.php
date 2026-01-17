<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/config.php';

header('Content-Type: application/json');

// 管理者認証チェックが必要
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Manager (1) and Staff (2) are allowed
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] != ROLE_MANAGER && $_SESSION['user']['role'] != ROLE_STAFF)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$room_id = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
$check_in = filter_input(INPUT_GET, 'check_in');
$check_out = filter_input(INPUT_GET, 'check_out');

if (!$room_id || !$check_in || !$check_out) {
    echo json_encode(['available' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $sql = "SELECT b.id FROM bookings b
            JOIN booking_rooms br ON b.id = br.booking_id
            WHERE br.room_id = :room_id
            AND b.status = 'confirmed'
            AND (b.check_in_date < :check_out_date AND b.check_out_date > :check_in_date)";

    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':room_id' => $room_id,
        ':check_in_date' => $check_in,
        ':check_out_date' => $check_out
    ]);

    if ($stmt->fetch()) {
        echo json_encode(['available' => false, 'message' => '既に予約が入っています。']);
    } else {
        echo json_encode(['available' => true, 'message' => '空室です。']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
