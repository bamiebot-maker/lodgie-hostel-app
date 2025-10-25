<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php'; // For notifications

// --- Page-Specific Logic ---
protect_page('landlord');
$page_title = "Manage Bookings";
$landlord_id = $_SESSION['user_id'];

// --- Handle Booking Status Update (Approve/Reject) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
    } else {
        $booking_id = (int) $_POST['booking_id'];
        $new_status = sanitize($_POST['new_status']);
        $tenant_id = (int) $_POST['tenant_id']; // Get tenant_id from form
        
        // Validate status
        if (!in_array($new_status, ['confirmed', 'cancelled'])) {
            $_SESSION['error_flash'] = 'Invalid status update.';
        } else {
            try {
                // Update booking status
                $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ? AND landlord_id = ?");
                $stmt->execute([$new_status, $booking_id, $landlord_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_flash'] = 'Booking status updated successfully.';
                    
                    // Notify Tenant
                    $notif_title = ($new_status === 'confirmed') ? 'Booking Confirmed' : 'Booking Cancelled';
                    $notif_msg = "Your booking (#$booking_id) has been $new_status by the landlord.";
                    $notif_type = ($new_status === 'confirmed') ? 'success' : 'danger';
                    
                    create_notification($pdo, $tenant_id, $notif_title, $notif_msg, $notif_type, 'tenant/bookings.php');

                } else {
                    $_SESSION['error_flash'] = 'Could not update booking. It may not exist or belong to you.';
                }
            } catch (PDOException $e) {
                $_SESSION['error_flash'] = 'Database error. ' . $e->getMessage();
            }
        }
    }
    redirect('landlord/bookings.php');
}


// --- Fetch all bookings for this landlord's hostels ---
$stmt = $pdo->prepare("
    SELECT 
        b.*, 
        h.name as hostel_name,
        u.name as tenant_name,
        u.email as tenant_email,
        u.phone as tenant_phone
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN users u ON b.tenant_id = u.id
    WHERE b.landlord_id = ?
    ORDER BY 
        CASE b.status
            WHEN 'paid' THEN 1
            WHEN 'pending' THEN 2
            WHEN 'confirmed' THEN 3
            ELSE 4
        END, 
        b.created_at DESC
");
$stmt->execute([$landlord_id]);
$bookings = $stmt->fetchAll();

$csrf_token = generate_csrf_token();

// --- Header ---
get_header($pdo); 
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Manage Bookings</h1>

    <?php display_flash_messages(); ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">All Booking Requests</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($bookings)): ?>
                <div class="text-center p-5">
                    <i class="bi bi-calendar-x" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">You have no bookings yet.</h4>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tenant</th>
                                <th>Hostel</th>
                                <th>Booking Dates</th>
                                <th>Total Price</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo sanitize($booking['tenant_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo sanitize($booking['tenant_email']); ?></small>
                                        <br>
                                        <small class="text-muted"><?php echo sanitize($booking['tenant_phone']); ?></small>
                                    </td>
                                    <td><?php echo sanitize($booking['hostel_name']); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($booking['start_date'])); ?>
                                        <br>
                                        <small class="text-muted">(for <?php echo $booking['duration_months']; ?> mo)</small>
                                    </td>
                                    <td class="fw-bold text-orange">
                                        <?php echo format_price($booking['total_price']); ?>
                                    </td>
                                    <td>
                                        <span class="badge fs-6 bg-<?php 
                                            $status_map = ['pending' => 'warning', 'paid' => 'success', 'confirmed' => 'primary', 'cancelled' => 'danger', 'completed' => 'secondary'];
                                            echo $status_map[$booking['status']] ?? 'light'; 
                                        ?>">
                                            <?php echo ucfirst(sanitize($booking['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($booking['status'] === 'paid'): // Tenant has paid, landlord needs to confirm ?>
                                            <form action="bookings.php" method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="tenant_id" value="<?php echo $booking['tenant_id']; ?>">
                                                <input type="hidden" name="new_status" value="confirmed">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-success" title="Confirm Booking">
                                                    <i class="bi bi-check-lg"></i> Confirm
                                                </button>
                                            </form>
                                            <form action="bookings.php" method="POST" class="d-inline ms-1">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="tenant_id" value="<?php echo $booking['tenant_id']; ?>">
                                                <input type="hidden" name="new_status" value="cancelled">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-danger" title="Cancel Booking">
                                                    <i class="bi bi-x-lg"></i> Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($booking['status'] === 'pending'): ?>
                                            <span class="text-muted small">Awaiting payment</span>
                                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                                            <span class="text-success small"><i class="bi bi-check-all"></i> Confirmed</span>
                                        <?php else: ?>
                                            <span class="text-muted small">No action needed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// --- Footer ---
get_footer(); 
?>