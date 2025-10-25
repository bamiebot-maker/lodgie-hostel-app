<?php
/**
 * Paystack Webhook
 *
 * Server-to-server verification. This is the most reliable way
 * to confirm a payment, as it doesn't depend on the user's browser.
 */

// --- Core Includes ---
// Note: No HTML output on this page, just processing.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

// 1. Retrieve the request's body
$input = @file_get_contents("php://input");
$event = json_decode($input);

// 2. Validate the event
if (empty($input)) {
    http_response_code(400); // Bad Request
    error_log('Paystack Webhook: Empty payload');
    exit('Empty payload');
}

// 3. Verify the signature
// This is crucial to ensure the request is from Paystack
if (!isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
    http_response_code(400); // Bad Request
    error_log('Paystack Webhook: Missing signature');
    exit('Missing signature');
}

$paystack_signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];
$expected_signature = hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY);

if ($paystack_signature !== $expected_signature) {
    // Invalid signature
    http_response_code(401); // Unauthorized
    error_log('Paystack Webhook: Invalid signature');
    exit('Invalid signature');
}

// 4. Check the event type
if (!isset($event->event)) {
    http_response_code(400); // Bad Request
    error_log('Paystack Webhook: Missing event type');
    exit('Missing event type');
}

// We only care about successful charges
if ($event->event === 'charge.success') {
    
    // 5. Get data from the event
    $data = $event->data;
    $paystack_reference = $data->reference;
    $amount_paid = (int) $data->amount / 100; // Convert from kobo
    $booking_id = $data->metadata->booking_id ?? 0;
    $tenant_id = $data->metadata->tenant_id ?? 0;

    // Check for essential metadata
    if ($booking_id == 0 || $tenant_id == 0) {
        http_response_code(400);
        error_log('Paystack Webhook: Missing metadata (booking_id or tenant_id) for ref ' . $paystack_reference);
        exit('Missing metadata');
    }

    try {
        // 6. Check our DB to prevent double-processing
        $stmt_check = $pdo->prepare("SELECT status FROM payments WHERE paystack_reference = ?");
        $stmt_check->execute([$paystack_reference]);
        $payment_status = $stmt_check->fetchColumn();

        if ($payment_status === 'success') {
            // Already processed. Send 200 OK to Paystack.
            http_response_code(200);
            exit('Already processed');
        }

        // 7. Update Database
        $pdo->beginTransaction();

        // Update payments table (or insert if callback failed to create it)
        // Using ON DUPLICATE KEY UPDATE for robustness
        $sql_pay = "
            INSERT INTO payments (booking_id, tenant_id, amount, paystack_reference, status, paid_at)
            VALUES (?, ?, ?, ?, 'success', NOW())
            ON DUPLICATE KEY UPDATE 
                status = 'success', 
                paid_at = NOW(), 
                amount = VALUES(amount),
                booking_id = VALUES(booking_id),
                tenant_id = VALUES(tenant_id)
        ";
        $stmt_pay = $pdo->prepare($sql_pay);
        $stmt_pay->execute([$booking_id, $tenant_id, $amount_paid, $paystack_reference]);

        // Update bookings table
        $stmt_book = $pdo->prepare("UPDATE bookings SET status = 'paid' WHERE id = ?");
        $stmt_book->execute([$booking_id]);
        
        // Get landlord_id for notification
        $stmt_details = $pdo->prepare("SELECT landlord_id FROM bookings WHERE id = ?");
        $stmt_details->execute([$booking_id]);
        $landlord_id = $stmt_details->fetchColumn();

        // 8. Send Notifications (same as callback)
        $formatted_amount = '₦' . number_format($amount_paid, 2); // Use a simple format
        
        create_notification($pdo, $tenant_id, 'Payment Successful', "Your payment of $formatted_amount for booking #$booking_id was successful.", 'success', 'tenant/bookings.php');
        create_notification($pdo, $landlord_id, 'New Booking Payment', "You received a new payment of $formatted_amount for booking #$booking_id.", 'success', 'landlord/bookings.php');
        
        $admin_id = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
        if ($admin_id) {
            create_notification($pdo, $admin_id, 'New Payment Received', "A new payment of $formatted_amount was received for booking #$booking_id.", 'info', 'admin/payments.php');
        }

        $pdo->commit();

        // 9. Send 200 OK response to Paystack
        http_response_code(200);
        exit('Webhook processed successfully');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Log error and respond with 500
        error_log("Paystack Webhook DB Error: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        exit('Database error');
    }

} else {
    // Not an event we're interested in, but acknowledge it
    http_response_code(200);
    exit('Event received but not processed: ' . $event->event);
}
?>