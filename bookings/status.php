<?php
$page_title = "Booking Status - Lodgie";
require_once '../includes/header-auth.php';

$booking_id = $_GET['id'] ?? 0;

// Get booking details
$booking = $db->fetch(
    "SELECT b.*, h.title as hostel_title, h.price_per_month, h.landlord_id,
     u.full_name as landlord_name, u.phone as landlord_phone,
     (SELECT filename FROM hostel_images WHERE hostel_id = h.id AND is_cover = 1 LIMIT 1) as cover_image
     FROM bookings b 
     JOIN hostels h ON b.hostel_id = h.id 
     JOIN users u ON h.landlord_id = u.id 
     WHERE b.id = ? AND (b.student_id = ? OR h.landlord_id = ? OR ? = (SELECT id FROM users WHERE role = 'admin' LIMIT 1))",
    [$booking_id, $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]
);

if (!$booking) {
    $_SESSION['message'] = 'Booking not found';
    $_SESSION['message_type'] = 'danger';
    redirect('../index.php');
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Booking #<?php echo $booking_id; ?></h4>
                <span class="badge bg-<?php 
                    echo match($booking['status']) {
                        'pending' => 'warning',
                        'paid' => 'info',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'secondary',
                        default => 'secondary'
                    };
                ?> text-uppercase"><?php echo $booking['status']; ?></span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Hostel Information</h6>
                        <p class="mb-1"><strong><?php echo sanitize_input($booking['hostel_title']); ?></strong></p>
                        <p class="text-muted mb-0">₦<?php echo number_format($booking['price_per_month'], 2); ?> / month</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Booking Dates</h6>
                        <p class="mb-1"><strong>Check-in:</strong> <?php echo date('M j, Y', strtotime($booking['check_in'])); ?></p>
                        <p class="mb-0"><strong>Check-out:</strong> <?php echo date('M j, Y', strtotime($booking['check_out'])); ?></p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Landlord Contact</h6>
                        <p class="mb-1"><strong><?php echo sanitize_input($booking['landlord_name']); ?></strong></p>
                        <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo sanitize_input($booking['landlord_phone']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Booking Timeline</h6>
                        <p class="mb-1"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></p>
                        <?php if ($booking['updated_at']): ?>
                            <p class="mb-0"><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['updated_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (has_role('student') && $booking['status'] === 'pending'): ?>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Payment Required</h6>
                        <p class="mb-2">Please complete your payment to secure this booking.</p>
                        <form id="paymentForm" action="../payments/paystack_init.php" method="POST">
                            <?php echo CSRF::field(); ?>
                            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-credit-card me-2"></i>Pay Now - ₦<?php echo number_format($booking['price_per_month'], 2); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between">
                    <a href="../hostels/view.php?id=<?php echo $booking['hostel_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>View Hostel
                    </a>
                    <a href="../index.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>