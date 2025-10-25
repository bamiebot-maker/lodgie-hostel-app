<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

Auth::requireRole(['tenant']);
$page_title = "Booking Details";
require_once '../includes/headers/header_tenant.php';

$db = new Database();

// Get booking ID
$booking_id = intval($_GET['id'] ?? 0);
if (!$booking_id) {
    header('Location: bookings.php');
    exit;
}

// Get booking details
try {
    $stmt = $db->query("
        SELECT b.*, 
               h.name as hostel_name, h.address, h.city, h.state, h.price_per_month,
               h.school_proximity, h.landlord_id,
               u.first_name as landlord_first, u.last_name as landlord_last,
               u.email as landlord_email, u.phone as landlord_phone,
               p.id as payment_id, p.amount as paid_amount, p.payment_status, 
               p.paystack_reference, p.created_at as payment_date
        FROM bookings b
        JOIN hostels h ON b.hostel_id = h.id
        JOIN users u ON h.landlord_id = u.id
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE b.id = ? AND b.tenant_id = ?
    ", [$booking_id, $_SESSION['user_id']]);
    
    $booking = $stmt->fetch();
} catch (Exception $e) {
    $booking = null;
    error_log("Database error in booking_details.php: " . $e->getMessage());
}

if (!$booking) {
    $_SESSION['error'] = "Booking not found";
    header('Location: bookings.php');
    exit;
}
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="bookings.php">My Bookings</a></li>
            <li class="breadcrumb-item active">Booking #<?php echo $booking['id']; ?></li>
        </ol>
    </nav>

    <!-- Booking Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Booking Details</h2>
        <span class="badge bg-<?php 
            switch($booking['status']) {
                case 'confirmed': echo 'success'; break;
                case 'pending': echo 'warning'; break;
                case 'cancelled': echo 'danger'; break;
                case 'completed': echo 'info'; break;
                default: echo 'secondary';
            }
        ?> fs-6"><?php echo ucfirst($booking['status']); ?></span>
    </div>

    <div class="row">
        <!-- Booking Information -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Booking Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Booking ID:</th>
                                    <td>#<?php echo $booking['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Hostel:</th>
                                    <td><?php echo htmlspecialchars($booking['hostel_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Location:</th>
                                    <td><?php echo htmlspecialchars($booking['city'] . ', ' . $booking['state']); ?></td>
                                </tr>
                                <tr>
                                    <th>Check-in:</th>
                                    <td><?php echo format_date($booking['check_in_date']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Check-out:</th>
                                    <td><?php echo format_date($booking['check_out_date']); ?></td>
                                </tr>
                                <tr>
                                    <th>Duration:</th>
                                    <td><?php echo $booking['duration_months']; ?> months</td>
                                </tr>
                                <tr>
                                    <th>Monthly Price:</th>
                                    <td>₦<?php echo number_format($booking['price_per_month']); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td class="fw-bold text-orange">₦<?php echo number_format($booking['total_amount']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if ($booking['school_proximity']): ?>
                    <div class="mt-3">
                        <strong>School Proximity:</strong>
                        <span class="text-muted"><?php echo htmlspecialchars($booking['school_proximity']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <strong>Address:</strong>
                        <span class="text-muted"><?php echo htmlspecialchars($booking['address']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Information</h5>
                </div>
                <div class="card-body">
                    <?php if ($booking['payment_id']): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Payment Status:</th>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($booking['payment_status']) {
                                                    case 'success': echo 'success'; break;
                                                    case 'pending': echo 'warning'; break;
                                                    case 'failed': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>"><?php echo ucfirst($booking['payment_status']); ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Amount Paid:</th>
                                        <td class="fw-bold text-success">₦<?php echo number_format($booking['paid_amount']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Reference:</th>
                                        <td><code><?php echo $booking['paystack_reference']; ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Payment Date:</th>
                                        <td><?php echo format_date($booking['payment_date']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <?php if ($booking['payment_status'] === 'success'): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> 
                                Payment completed successfully. Your booking is confirmed!
                            </div>
                        <?php elseif ($booking['payment_status'] === 'pending'): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-clock"></i> 
                                Payment is pending. Please complete your payment to confirm booking.
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-credit-card text-muted fs-1"></i>
                            <h5 class="text-muted mt-3">No Payment Made</h5>
                            <p class="text-muted">You need to make payment to confirm your booking.</p>
                            
                            <?php if ($booking['status'] === 'pending'): ?>
                                <a href="../payments/paystack_init.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-orange btn-lg">
                                    <i class="bi bi-credit-card"></i> Make Payment
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Actions Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../hostel_details.php?id=<?php echo $booking['hostel_id']; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-eye"></i> View Hostel
                        </a>
                        
                        <?php if (!$booking['payment_id'] && $booking['status'] === 'pending'): ?>
                            <a href="../payments/paystack_init.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-orange">
                                <i class="bi bi-credit-card"></i> Make Payment
                            </a>
                        <?php endif; ?>

                        <?php if ($booking['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                <i class="bi bi-x-circle"></i> Cancel Booking
                            </button>
                        <?php endif; ?>

                        <a href="bookings.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Bookings
                        </a>
                    </div>
                </div>
            </div>

            <!-- Landlord Contact -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Hostel Owner</h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <i class="bi bi-person-circle fs-1 text-orange"></i>
                        <h6 class="mt-2"><?php echo htmlspecialchars($booking['landlord_first'] . ' ' . $booking['landlord_last']); ?></h6>
                        
                        <?php if ($booking['landlord_phone']): ?>
                            <p class="mb-1">
                                <i class="bi bi-telephone"></i> 
                                <a href="tel:<?php echo htmlspecialchars($booking['landlord_phone']); ?>">
                                    <?php echo htmlspecialchars($booking['landlord_phone']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <p class="mb-0">
                            <i class="bi bi-envelope"></i> 
                            <a href="mailto:<?php echo htmlspecialchars($booking['landlord_email']); ?>">
                                <?php echo htmlspecialchars($booking['landlord_email']); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Booking Timeline -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Booking Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item <?php echo $booking['status'] !== 'cancelled' ? 'active' : ''; ?>">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <small>Booking Created</small>
                                <p class="mb-0"><?php echo format_date($booking['created_at'], 'M j, Y g:i A'); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($booking['payment_id']): ?>
                        <div class="timeline-item <?php echo $booking['payment_status'] === 'success' ? 'active' : ''; ?>">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <small>Payment <?php echo $booking['payment_status'] === 'success' ? 'Completed' : 'Initiated'; ?></small>
                                <p class="mb-0"><?php echo format_date($booking['payment_date'], 'M j, Y g:i A'); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="timeline-item <?php echo $booking['status'] === 'confirmed' ? 'active' : ''; ?>">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <small>Booking Confirmed</small>
                                <p class="mb-0"><?php echo $booking['status'] === 'confirmed' ? format_date($booking['updated_at'], 'M j, Y g:i A') : 'Pending'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Booking Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this booking?</p>
                <p class="text-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    This action cannot be undone. You will need to create a new booking if you change your mind.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Booking</button>
                <a href="?action=cancel&id=<?php echo $booking['id']; ?>" class="btn btn-danger">Cancel Booking</a>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 20px;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -20px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #dee2e6;
    border: 2px solid #fff;
}
.timeline-item.active .timeline-marker {
    background-color: #f97316;
}
.timeline-content {
    margin-left: 0;
}
.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: -15px;
    top: 12px;
    bottom: -20px;
    width: 2px;
    background-color: #dee2e6;
}
</style>

<?php include '../includes/footer.php'; ?>