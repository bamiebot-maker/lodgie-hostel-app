<?php
require_once '../includes/config.php';
require_once '../includes/helpers.php';
require_once '../includes/auth.php';

require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php');
}

if (!CSRF::validate($_POST['csrf_token'])) {
    $_SESSION['message'] = 'Invalid CSRF token';
    $_SESSION['message_type'] = 'danger';
    redirect('../index.php');
}

$hostel_id = $_POST['hostel_id'] ?? 0;
$check_in = $_POST['check_in'] ?? '';
$check_out = $_POST['check_out'] ?? '';

// Validate dates
if (empty($check_in) || empty($check_out)) {
    $_SESSION['message'] = 'Please select check-in and check-out dates';
    $_SESSION['message_type'] = 'danger';
    redirect('../hostels/view.php?id=' . $hostel_id);
}

if (strtotime($check_in) >= strtotime($check_out)) {
    $_SESSION['message'] = 'Check-out date must be after check-in date';
    $_SESSION['message_type'] = 'danger';
    redirect('../hostels/view.php?id=' . $hostel_id);
}

// Check hostel availability
$hostel = $db->fetch(
    "SELECT * FROM hostels WHERE id = ? AND is_published = 1 AND is_verified = 1 AND available_rooms > 0",
    [$hostel_id]
);

if (!$hostel) {
    $_SESSION['message'] = 'Hostel not available for booking';
    $_SESSION['message_type'] = 'danger';
    redirect('../search.php');
}

// Create booking
$booking_id = $db->insert(
    "INSERT INTO bookings (student_id, hostel_id, check_in, check_out, status, created_at) 
     VALUES (?, ?, ?, ?, 'pending', NOW())",
    [$_SESSION['user_id'], $hostel_id, $check_in, $check_out]
);

if ($booking_id) {
    // Create notification for landlord
    $db->insert(
        "INSERT INTO notifications (user_id, type, title, body, created_at) 
         VALUES (?, 'new_booking', 'New Booking Request', 'You have a new booking request #{$booking_id}', NOW())",
        [$hostel['landlord_id']]
    );

    $_SESSION['message'] = 'Booking created successfully! Please proceed to payment.';
    $_SESSION['message_type'] = 'success';
    redirect('status.php?id=' . $booking_id);
} else {
    $_SESSION['message'] = 'Failed to create booking';
    $_SESSION['message_type'] = 'danger';
    redirect('../hostels/view.php?id=' . $hostel_id);
}
?>