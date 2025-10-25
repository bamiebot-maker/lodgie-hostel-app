<?php
$page_title = "Booking Details - Lodgie";
require_once '../includes/header-auth.php';

$booking_id = $_GET['id'] ?? 0;

if (!$booking_id) {
    redirect('bookings.php');
}

// Get booking details with access control
if (has_role('student')) {
    $booking = $db->fetch(
        "SELECT b.*, h.title as hostel_title, h.price_per_month, h.landlord_id,
                u.full_name as landlord_name, u.phone as landlord_phone, u.email as landlord_email,
                (SELECT filename FROM hostel_images WHERE hostel_id = h.id AND is_cover = 1 LIMIT 1) as cover_image
         FROM bookings b 
         JOIN hostels h ON b.hostel_id = h.id 
         JOIN users u ON h.landlord_id = u.id 
         WHERE b.id = ? AND b.student_id = ?",
        [$booking_id, $_SESSION['user_id']]
    );
} elseif (has_role('landlord')) {
    $booking = $db->fetch(
        "SELECT b.*, h.title as hostel_title, h.price_per_month, h.landlord_id,
                u.full_name as student_name, u.phone as student_phone, u.email as student_email,
                (SELECT filename FROM hostel_images WHERE hostel_id = h.id AND is_cover = 1 LIMIT 1) as cover_image
         FROM bookings b 
         JOIN hostels h ON b.hostel_id = h.id 
         JOIN users u ON b.student_id = u.id 
         WHERE b.id = ? AND h.landlord_id = ?",
        [$booking_id, $_SESSION['user_id']]
    );
} else {
    redirect('index.php');
}

if (!$booking) {
    $_SESSION['message'] = 'Booking not found';
    $_SESSION['message_type'] = 'danger';
    redirect('bookings.php');
}
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="bookings.php">Bookings</a></li>
                <li class="breadcrumb-item active">Booking #<?php echo $booking_id; ?></li>
            </ol>
        </nav>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">Booking #<?php echo $booking_id; ?></h1>
            <span class="badge bg-<?php 
                echo match($booking['status']) {
                    'pending' => 'warning',
                    'paid' => 'info',
                    'confirmed' => 'success',
                    'cancelled' => 'danger',
                    'completed' => 'secondary',
                    default => 'secondary'
                };
            ?> text-uppercase fs-6"><?php echo $booking['status']; ?></span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Booking Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Hostel Details</h6>
                        <p class="mb-1"><strong><?php echo sanitize_input($booking['hostel_title']); ?></strong></p>
                        <p class="text-muted mb-3">₦<?php echo number_format($booking['price_per_month'], 2); ?> per month</p>
                        
                        <h6>Booking Dates</h6>
                        <p class="mb-1"><strong>Check-in:</strong> <?php echo date('F j, Y', strtotime($booking['check_in'])); ?></p>
                        <p class="mb-3"><strong>Check-out:</strong> <?php echo date('F j, Y', strtotime($booking['check_out'])); ?></p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6><?php echo has_role('student') ? 'Landlord Contact' : 'Student Information'; ?></h6>
                        <?php if (has_role('student')): ?>
                            <p class="mb-1"><strong><?php echo sanitize_input($booking['landlord_name']); ?></strong></p>
                            <p class="mb-1"><i class="fas fa-phone me-2"></i><?php echo sanitize_input($booking['landlord_phone']); ?></p>
                            <p class="mb-3"><i class="fas fa-envelope me-2"></i><?php echo sanitize_input($booking['landlord_email']); ?></p>
                        <?php else: ?>
                            <p class="mb-1"><strong><?php echo sanitize_input($booking['student_name']); ?></strong></p>
                            <p class="mb-1"><i class="fas fa-phone me-2"></i><?php echo sanitize_input($booking['student_phone']); ?></p>
                            <p class="mb-3"><i class="fas fa-envelope me-2"></i><?php echo sanitize_input($booking['student_email']); ?></p>
                        <?php endif; ?>
                        
                        <h6>Timeline</h6>
                        <p class="mb-1"><strong>Created:</strong> <?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></p>
                        <?php if ($booking['updated_at']): ?>
                            <p class="mb-0"><strong>Updated:</strong> <?php echo date('F j, Y g:i A', strtotime($booking['updated_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Section for Students -->
        <?php if (has_role('student') && $booking['status'] === 'pending'): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Required</h5>
                </div>
                <div class="card-body">
                    <p>Please complete your payment to secure this booking. Your booking will be confirmed once payment is verified.</p>
                    <form id="paymentForm" action="../payments/paystack_init.php" method="POST">
                        <?php echo CSRF::field(); ?>
                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-credit-card me-2"></i>Pay Now - ₦<?php echo number_format($booking['price_per_month'], 2); ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Confirmation Section for Landlords -->
        <?php if (has_role('landlord') && $booking['status'] === 'paid'): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Confirm Booking</h5>
                </div>
                <div class="card-body">
                    <p>This booking has been paid. Please confirm to finalize the booking and update room availability.</p>
                    <form action="confirm.php" method="POST">
                        <?php echo CSRF::field(); ?>
                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Confirm Booking
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Hostel Image -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Hostel</h5>
            </div>
            <div class="card-body text-center">
                <?php if ($booking['cover_image']): ?>
                    <img src="../uploads/hostels/<?php echo $booking['cover_image']; ?>" 
                         class="img-fluid rounded mb-3" alt="<?php echo sanitize_input($booking['hostel_title']); ?>"
                         style="max-height: 200px; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-secondary rounded d-flex align-items-center justify-content-center mb-3" 
                         style="height: 200px;">
                        <i class="fas fa-home fa-3x text-white"></i>
                    </div>
                <?php endif; ?>
                <h6><?php echo sanitize_input($booking['hostel_title']); ?></h6>
                <a href="../hostels/view.php?id=<?php echo $booking['hostel_id']; ?>" class="btn btn-outline-primary btn-sm">
                    View Hostel Details
                </a>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="bookings.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                    </a>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                    
                    <?php if (has_role('student') && $booking['status'] === 'pending'): ?>
                        <a href="cancel.php?id=<?php echo $booking_id; ?>" class="btn btn-outline-danger"
                           onclick="return confirm('Are you sure you want to cancel this booking?')">
                            <i class="fas fa-times me-2"></i>Cancel Booking
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>