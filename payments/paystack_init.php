<?php
/**
 * Paystack Initialization
 *
 * This script is called when a user clicks "Pay Now".
 * It takes booking details, creates a payment record in the DB,
 * and redirects the user to Paystack's payment page.
 */

// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Protect Page ---
// We assume this is called from a form on a tenant-protected page.
// The form should POST the booking_id here.
protect_page('tenant');

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Get Booking ID from POST
    if (empty($_POST['booking_id'])) {
        $_SESSION['error_flash'] = 'Invalid booking attempt.';
        redirect('tenant/bookings.php');
    }
    
    $booking_id = (int) $_POST['booking_id'];
    $tenant_id = (int) $_SESSION['user_id'];
    $tenant_email = $_SESSION['user_email'];

    try {
        // 2. Fetch Booking Details
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND tenant_id = ? AND status = 'pending'");
        $stmt->execute([$booking_id, $tenant_id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['error_flash'] = 'Booking not found or already processed.';
            redirect('tenant/bookings.php');
        }

        $amount_in_kobo = (int) ($booking['total_price'] * 100); // Paystack expects amount in Kobo
        $reference = 'lodgie_bk_' . $booking_id . '_' . time();
        $callback_url = BASE_URL . '/payments/paystack_callback.php';

        // 3. Create a 'pending' payment record
        $stmt_pay = $pdo->prepare("
            INSERT INTO payments (booking_id, tenant_id, amount, paystack_reference, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt_pay->execute([
            $booking_id, 
            $tenant_id, 
            $booking['total_price'], 
            $reference
        ]);
        
        // 4. Prepare data for Paystack API
        $post_data = [
            'email' => $tenant_email,
            'amount' => $amount_in_kobo,
            'reference' => $reference,
            'callback_url' => $callback_url,
            'metadata' => [
                'booking_id' => $booking_id,
                'tenant_id' => $tenant_id,
                'site_name' => SITE_NAME
            ]
        ];

        // 5. Initialize cURL
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($post_data),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
                "Content-Type: application/json"
            ],
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        // 6. Handle Response
        if ($err) {
            // cURL error
            $_SESSION['error_flash'] = 'Payment service error. Please try again. ' . $err;
            redirect('tenant/bookings.php');
        } else {
            $result = json_decode($response);
            if ($result->status == true) {
                // Redirect to Paystack
                header('Location: ' . $result->data->authorization_url);
                exit();
            } else {
                // Paystack API error
                $_SESSION['error_flash'] = 'Payment initialization failed: ' . $result->message;
                redirect('tenant/bookings.php');
            }
        }

    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Database error during payment init.';
        error_log('Paystack Init Error: ' . $e->getMessage());
        redirect('tenant/bookings.php');
    }

} else {
    // Not a POST request
    redirect('tenant/dashboard.php');
}
?>