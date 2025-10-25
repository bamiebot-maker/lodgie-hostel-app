<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php'; // For notifications

// --- Page-Specific Logic ---
protect_page('tenant');
$tenant_id = $_SESSION['user_id'];

// --- Handle Booking Cancellation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    
    // 1. Verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
        redirect('tenant/bookings.php');
    }
    
    $booking_id = (int) $_POST['booking_id'];
    
    try {
        // 2. Find the booking
        $stmt_find = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND tenant_id = ?");
        $stmt_find->execute([$booking_id, $tenant_id]);
        $booking = $stmt_find->fetch();

        if (!$booking) {
            $_SESSION['error_flash'] = 'Booking not found.';
            redirect('tenant/bookings.php');
        }

        // 3. Only allow cancellation if status is 'pending'
        if ($booking['status'] !== 'pending') {
            $_SESSION['error_flash'] = 'This booking cannot be cancelled as it has already been processed.';
            redirect('tenant/bookings.php');
        }

        // 4. Update status to 'cancelled'
        $stmt_cancel = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND tenant_id = ?");
        $stmt_cancel->execute([$booking_id, $tenant_id]);

        if ($stmt_cancel->rowCount() > 0) {
            $_SESSION['success_flash'] = 'Booking #' . $booking_id . ' has been successfully cancelled.';
            
            // Notify Landlord
            $landlord_id = $booking['landlord_id'];
            create_notification(
                $pdo, 
                $landlord_id, 
                'Booking Cancelled', 
                "Tenant " . $_SESSION['user_name'] . " has cancelled their pending booking (#$booking_id).", 
                'warning', 
                'landlord/bookings.php'
            );
        } else {
            $_SESSION['error_flash'] = 'Failed to cancel booking.';
        }

    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'A database error occurred.';
        error_log('Cancel Booking Error: ' . $e->getMessage());
    }
    
    redirect('tenant/bookings.php');

} else {
    // Redirect if accessed directly
    redirect('tenant/bookings.php');
}
?>