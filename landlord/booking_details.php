<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php'; // $pdo is created here
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php'; // For sending notifications

// --- Page-Specific Logic ---
protect_page('landlord');
$page_title = "Booking Details";
$landlord_id = $_SESSION['user_id'];
$booking_id = (int)($_GET['id'] ?? 0);

if ($booking_id === 0) {
    $_SESSION['error_flash'] = 'Invalid booking ID.';
    redirect('landlord/bookings.php');
}

// --- Fetch Booking Details ---
// Join necessary tables to get all relevant info
$stmt = $pdo->prepare("
    SELECT
        b.*,
        h.name as hostel_name,
        h.address as hostel_address,
        u_tenant.name as tenant_name,
        u_tenant.email as tenant_email,
        u_tenant.phone as tenant_phone
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN users u_tenant ON b.tenant_id = u_tenant.id
    WHERE b.id = ? AND b.landlord_id = ?
");
$stmt->execute([$booking_id, $landlord_id]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['error_flash'] = 'Booking not found or you do not have permission to view it.';
    redirect('landlord/bookings.php');
}

// --- Handle Status Update (using the logic from bookings.php page) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
    } else {
        // We already have $booking_id from GET
        $new_status = sanitize($_POST['new_status']);
        $tenant_id = (int) $booking['tenant_id']; // Get tenant_id from the fetched booking data

        // Validate status
        if (!in_array($new_status, ['confirmed', 'cancelled'])) {
            $_SESSION['error_flash'] = 'Invalid status update.';
        } else {
            try {
                // Update booking status
                $stmt_update = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ? AND landlord_id = ?");
                $stmt_update->execute([$new_status, $booking_id, $landlord_id]);

                if ($stmt_update->rowCount() > 0) {
                    $_SESSION['success_flash'] = 'Booking status updated successfully.';

                    // Notify Tenant
                    $notif_title = ($new_status === 'confirmed') ? 'Booking Confirmed 🎉' : 'Booking Cancelled';
                    $notif_msg = "Your booking (#$booking_id for " . $booking['hostel_name'] . ") has been $new_status by the landlord.";
                    $notif_type = ($new_status === 'confirmed') ? 'success' : 'danger';

                    create_notification($pdo, $tenant_id, $notif_title, $notif_msg, $notif_type, 'tenant/bookings.php');

                    // Refresh the booking data after update
                    $stmt->execute([$booking_id, $landlord_id]);
                    $booking = $stmt->fetch();

                } else {
                    $_SESSION['error_flash'] = 'Could not update booking status.';
                }
            } catch (PDOException $e) {
                $_SESSION['error_flash'] = 'Database error. ' . $e->getMessage();
            }
        }
    }
    // Redirect back to this same details page to show updated status
    redirect('landlord/booking_details.php?id=' . $booking_id);
}


$csrf_token = generate_csrf_token();

// --- Header ---
// **IMPORTANT:** Pass $pdo
get_header($pdo);
?>

<nav aria-label="breadcrumb mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="bookings.php">Bookings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Booking #<?php echo $booking['id']; ?></li>
        </ol>
    </nav>

    <h1 class="h3 mb-4">Booking Details #<?php echo $booking['id']; ?></h1>

    <?php display_flash_messages(); ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Booking Information</h5>
            <div>
                 <span class="badge fs-6 bg-<?php
                    $status_map = ['pending' => 'warning text-dark', 'paid' => 'success', 'confirmed' => 'primary', 'cancelled' => 'danger', 'completed' => 'secondary'];
                    echo $status_map[$booking['status']] ?? 'light';
                 ?>">
                    Status: <?php echo ucfirst(sanitize($booking['status'])); ?>
                </span>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="text-orange">Hostel Details</h6>
                    <p class="mb-1"><strong>Name:</strong> <?php echo sanitize($booking['hostel_name']); ?></p>
                    <p class="mb-1"><strong>Address:</strong> <?php echo sanitize($booking['hostel_address']); ?></p>
                    <p class="mb-1"><strong>Booked On:</strong> <?php echo date('M j, Y, g:i a', strtotime($booking['created_at'])); ?></p>
                </div>

                <div class="col-md-6">
                    <h6 class="text-orange">Tenant Details</h6>
                    <p class="mb-1"><strong>Name:</strong> <?php echo sanitize($booking['tenant_name']); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo sanitize($booking['tenant_email']); ?></p>
                    <p class="mb-1"><strong>Phone:</strong> <?php echo sanitize($booking['tenant_phone']); ?></p>
                </div>

                <div class="col-12">
                     <hr>
                    <h6 class="text-orange">Booking Period & Price</h6>
                     <p class="mb-1"><strong>Move-in Date:</strong> <?php echo date('l, M j, Y', strtotime($booking['start_date'])); ?></p>
                     <p class="mb-1"><strong>Duration:</strong> <?php echo $booking['duration_months']; ?> month(s)</p>
                     <p class="mb-1"><strong>Total Price:</strong> <span class="fw-bold fs-5"><?php echo format_price($booking['total_price']); ?></span></p>
                </div>

            </div>
        </div>

        <?php if ($booking['status'] === 'paid'): // Only show actions if tenant has paid ?>
        <div class="card-footer bg-light text-end p-3">
             <form action="booking_details.php?id=<?php echo $booking_id; ?>" method="POST" class="d-inline me-2">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="new_status" value="confirmed">
                <button type="submit" name="update_status" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i> Confirm Booking
                </button>
            </form>
            <form action="booking_details.php?id=<?php echo $booking_id; ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this paid booking?');">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="new_status" value="cancelled">
                <button type="submit" name="update_status" class="btn btn-danger">
                    <i class="bi bi-x-lg me-1"></i> Cancel Booking
                </button>
            </form>
        </div>
        <?php elseif ($booking['status'] === 'pending'): ?>
         <div class="card-footer bg-light text-center p-3">
            <span class="text-muted fst-italic">Awaiting payment from tenant...</span>
         </div>
        <?php elseif ($booking['status'] === 'confirmed'): ?>
         <div class="card-footer bg-success-subtle text-center p-3">
            <span class="text-success fw-bold"><i class="bi bi-check-all me-1"></i> Booking Confirmed</span>
         </div>
        <?php else: ?>
         <div class="card-footer bg-light text-center p-3">
             <span class="text-muted fst-italic">No further actions needed for status: <?php echo sanitize($booking['status']); ?></span>
         </div>
        <?php endif; ?>

    </div>

<?php
// --- Footer ---
get_footer();
?>