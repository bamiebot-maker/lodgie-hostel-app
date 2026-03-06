<?php
/**
 * Paystack Callback
 *
 * This is the URL Paystack redirects to after payment.
 * It verifies the transaction, updates the DB, and sends notifications.
 */

// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

// --- Page-Specific Logic ---
$page_title = "Payment Verification";
get_header(); // Show a header

// 1. Get reference from URL
$reference = $_GET['reference'] ?? '';
if (empty($reference)) {
    echo '<div class="container p-5 text-center"><div class="alert alert-danger">Invalid payment reference.</div></div>';
    get_footer();
    exit();
}

try {
    // 2. Verify Transaction with Paystack
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "Cache-Control: no-cache",
        ],
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        // cURL error
        echo '<div class="container p-5 text-center"><div class="alert alert-danger">Payment verification service is down. Please contact support with your reference: ' . sanitize($reference) . '</div></div>';
        error_log("Paystack Verify cURL Error: " . $err);
        get_footer();
        exit();
    }
    
    $result = json_decode($response);

    // 3. Check Transaction Status
    if ($result->status == true && $result->data->status == 'success') {
        // Payment is successful
        
        // 4. Get data from Paystack response
        $paystack_reference = $result->data->reference;
        $amount_paid = (int) $result->data->amount / 100; // Convert back from kobo
        $booking_id = $result->data->metadata->booking_id ?? 0;
        $tenant_id = $result->data->metadata->tenant_id ?? 0;

        // Security: Ensure logged-in user matches payment
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $tenant_id) {
            echo '<div class="container p-5 text-center"><div class="alert alert-danger">Security error: You are not authorized to verify this payment.</div></div>';
            get_footer();
            exit();
        }

        // Security: Verify amount matches the booking price
        $stmt_amt = $pdo->prepare("SELECT total_price FROM bookings WHERE id = ?");
        $stmt_amt->execute([$booking_id]);
        $expected_amount = (float) $stmt_amt->fetchColumn();

        if ($expected_amount != $amount_paid) {
             echo '<div class="container p-5 text-center"><div class="alert alert-danger">Payment amount mismatch. Please contact support.</div></div>';
             get_footer();
             exit();
        }

        // 5. Check our DB to prevent double-processing
        $stmt_check = $pdo->prepare("SELECT status FROM payments WHERE paystack_reference = ?");
        $stmt_check->execute([$paystack_reference]);
        $payment_status = $stmt_check->fetchColumn();

        if ($payment_status === 'success') {
            // Already processed (e.g., by webhook)
            echo '<div class="container p-5 text-center">
                    <div class="alert alert-success">Your payment has already been confirmed.</div>
                    <a href="' . BASE_URL . '/tenant/bookings.php" class="btn btn-orange">View My Bookings</a>
                  </div>';
            get_footer();
            exit();
        }

        // 6. Update Database
        $pdo->beginTransaction();

        // Update payments table
        $stmt_pay = $pdo->prepare("UPDATE payments SET status = 'success', paid_at = NOW() WHERE paystack_reference = ?");
        $stmt_pay->execute([$paystack_reference]);

        // Update bookings table
        $stmt_book = $pdo->prepare("UPDATE bookings SET status = 'paid' WHERE id = ?");
        $stmt_book->execute([$booking_id]);
        
        // Get booking details for notifications
        $stmt_details = $pdo->prepare("SELECT landlord_id, hostel_id FROM bookings WHERE id = ?");
        $stmt_details->execute([$booking_id]);
        $booking_details = $stmt_details->fetch();
        $landlord_id = $booking_details['landlord_id'];

        // 7. Send Notifications
        $formatted_amount = format_price($amount_paid);
        $link = 'tenant/bookings.php';
        
        // To Tenant
        create_notification($pdo, $tenant_id, 'Payment Successful', "Your payment of $formatted_amount for booking #$booking_id was successful.", 'success', $link);

        // To Landlord
        $link = 'landlord/bookings.php';
        create_notification($pdo, $landlord_id, 'New Booking Payment', "You received a new payment of $formatted_amount for booking #$booking_id.", 'success', $link);

        // To Admin - alert all admins
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $admin_id) {
            $link = 'admin/payments.php';
            create_notification($pdo, $admin_id, 'New Payment Received', "A new payment of $formatted_amount was received for booking #$booking_id.", 'info', $link);
        }

        $pdo->commit();
        
        // 8. Show Success Message
        echo '<div class="container p-5 text-center">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                <h2 class="mt-3">Payment Successful!</h2>
                <p class="lead">Your booking has been confirmed. You will receive a confirmation shortly.</p>
                <a href="' . BASE_URL . '/tenant/bookings.php" class="btn btn-orange">View My Bookings</a>
              </div>';

    } else {
        // Payment failed or was abandoned
        
        // Update payment record in DB
        $stmt_fail = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE paystack_reference = ?");
        $stmt_fail->execute([$reference]);

        echo '<div class="container p-5 text-center">
                <i class="bi bi-x-circle-fill text-danger" style="font-size: 5rem;"></i>
                <h2 class="mt-3">Payment Failed</h2>
                <p class="lead">' . sanitize($result->data->gateway_response ?? 'Your payment could not be processed.') . '</p>
                <a href="' . BASE_URL . '/tenant/bookings.php" class="btn btn-outline-orange">Try Again</a>
              </div>';
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // DB error
    echo '<div class="container p-5 text-center"><div class="alert alert-danger">A critical error occurred after your payment. Please contact support with your reference: ' . sanitize($reference) . '</div></div>';
    error_log("Paystack Callback DB Error: " . $e->getMessage());
} catch (Exception $e) {
    // Other errors
    echo '<div class="container p-5 text-center"><div class="alert alert-danger">An unexpected error occurred.</div></div>';
    error_log("Paystack Callback General Error: " . $e->getMessage());
}

get_footer();
?>