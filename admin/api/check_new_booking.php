<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != ROLE_MANAGER) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Check for bookings created in the last 1 minute (or since last check if we passed a param, but simple poll is fine)
    // Actually, to avoid duplicate notifications, client should track the last ID it saw.
    // But for simplicity requested: "poll API to check new bookings"

    // Let's return the count of bookings created in the last 5 minutes (to be safe against polling delays)
    // Or better: Return the ID of the latest booking. The client compares it to what it has.

    $stmt = $dbh->query("SELECT id, created_at, guest_name FROM bookings ORDER BY id DESC LIMIT 1");
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'latest_id' => $latest ? $latest['id'] : 0,
        'latest_created_at' => $latest ? $latest['created_at'] : null,
        'guest_name' => $latest ? $latest['guest_name'] : ''
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'DB Error']);
}
