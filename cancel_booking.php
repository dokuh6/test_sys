<?php
require_once 'includes/init.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

validate_csrf_token();

$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$token = filter_input(INPUT_POST, 'token');
$booking_number = filter_input(INPUT_POST, 'booking_number');
$email = filter_input(INPUT_POST, 'email');

if (!$booking_id) {
    die("Invalid request: No Booking ID");
}

try {
    // Fetch booking
    $stmt = $dbh->prepare("SELECT * FROM bookings WHERE id = :id");
    $stmt->execute([':id' => $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("Booking not found");
    }

    // Verify Access
    $allowed = false;

    // 1. Token Check (from URL usually)
    if ($token && isset($booking['booking_token']) && $booking['booking_token'] === $token) {
        $allowed = true;
    }
    // 2. Number + Email Check
    elseif ($booking_number && $email) {
        // booking_number might not be in the initial schema but likely added later.
        // If the column exists, we check it. If not, this check fails safely.
        if (isset($booking['booking_number']) && $booking['booking_number'] === $booking_number && $booking['guest_email'] === $email) {
            $allowed = true;
        }
    }
    // 3. User ID Check (Logged in user)
    elseif (isset($_SESSION['user']['id']) && $booking['user_id'] == $_SESSION['user']['id']) {
        $allowed = true;
    }
    // 4. Admin Check
    elseif (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == 1) {
        $allowed = true;
    }
    // 5. Last Booking Session Check (for immediate cancellation after booking if needed, though safer to rely on token)
    elseif (isset($_SESSION['last_booking_id']) && $_SESSION['last_booking_id'] == $booking_id) {
        $allowed = true;
    }

    if (!$allowed) {
        // Log the attempt
        error_log("Unauthorized cancellation attempt for Booking ID: $booking_id");
        die("Permission denied. You are not authorized to cancel this booking.");
    }

    // Check if already cancelled
    if ($booking['status'] === 'cancelled') {
        // Already cancelled, just redirect
         $redirect_url = "confirm.php?booking_id=" . $booking_id;
         if ($token) $redirect_url .= "&token=" . $token;
         elseif ($booking_number && $email) $redirect_url .= "&booking_number=" . $booking_number . "&guest_email=" . $email;

         header("Location: " . $redirect_url);
         exit;
    }

    // Cancel
    // Use UPDATE with explicit status change
    $updateStmt = $dbh->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :id");
    $updateStmt->execute([':id' => $booking_id]);

    // Send Email
    send_cancellation_email($booking_id, $dbh);

    // Redirect
    $redirect_url = "confirm.php?booking_id=" . $booking_id;
    if ($token) $redirect_url .= "&token=" . $token;
    elseif ($booking_number && $email) $redirect_url .= "&booking_number=" . $booking_number . "&guest_email=" . $email;

    header("Location: " . $redirect_url);
    exit;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
