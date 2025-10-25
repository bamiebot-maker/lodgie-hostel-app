<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Only tenants can book hostels
Auth::requireRole(['tenant']);
$page_title = "Book Hostel";
require_once 'includes/headers/header_tenant.php';

$db = new Database();
$error = '';
$success = '';

// Get hostel ID
$hostel_id = intval($_GET['hostel_id'] ?? 0);
if (!$hostel_id) {
    header('Location: browse.php');
    exit;
}

// Get hostel details
try {
    $stmt = $db->query("
        SELECT h.*, u.first_name, u.last_name,
               (SELECT image_path FROM hostel_images WHERE hostel_id = h.id AND is_primary = TRUE LIMIT 1) as primary_image
        FROM hostels h 
        JOIN users u ON h.landlord_id = u.id 
        WHERE h.id = ? AND h.is_approved = TRUE AND h.is_active = TRUE AND h.available_rooms > 0
    ", [$hostel_id]);
    
    $hostel = $stmt->fetch();
} catch (Exception $e) {
    $hostel = null;
    error_log("Database error in booking.php: " . $e->getMessage());
}

if (!$hostel) {
    $_SESSION['error'] = "Hostel not available for booking";
    header('Location: browse.php');
    exit;
}

// Handle booking form submission
if ($_POST) {
    try {
        // Validate required fields
        $required = ['check_in_date', 'duration_months'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Validate dates
        $check_in_date = $_POST['check_in_date'];
        $duration_months = intval($_POST['duration_months']);
        
        if ($duration_months < 1 || $duration_months > 12) {
            throw new Exception("Duration must be between 1 and 12 months");
        }

        // Calculate check-out date and total amount
        $check_out_date = date('Y-m-d', strtotime($check_in_date . " + $duration_months months"));
        $total_amount = $hostel['price_per_month'] * $duration_months;

        // Check if check-in date is in the future
        if (strtotime($check_in_date) < strtotime('today')) {
            throw new Exception("Check-in date must be in the future");
        }

        // Check room availability
        if ($hostel['available_rooms'] < 1) {
            throw new Exception("No rooms available in this hostel");
        }

        // Create booking
        $sql = "INSERT INTO bookings (tenant_id, hostel_id, check_in_date, check_out_date, duration_months, total_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        
        $db->query($sql, [
            $_SESSION['user_id'],
            $hostel_id,
            $check_in_date,
            $check_out_date,
            $duration_months,
            $total_amount
        ]);

        $booking_id = $db->lastInsertId();

        // Create notification
        require_once 'includes/notifications.php';
        $notification = new Notification();
        $notification->notifyBookingCreated($booking_id);

        $_SESSION['success'] = "Booking created successfully! Please complete payment to confirm your reservation.";
        header('Location: tenant/booking_details.php?id=' . $booking_id);
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-orange text-white">
                    <h4 class="card-title mb-0">Book Hostel</h4>
                </div>
                <div class="card-body">
                    <!-- Hostel Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <?php if ($hostel['primary_image']): ?>
                                <img src="uploads/hostels/<?php echo $hostel['primary_image']; ?>" 
                                     class="img-fluid rounded" 
                                     alt="<?php echo htmlspecialchars($hostel['name']); ?>">
                            <?php else: ?>
                                <div class="bg-secondary rounded d-flex align-items-center justify-content-center" 
                                     style="height: 120px;">
                                    <i class="bi bi-house text-white fs-1"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h4><?php echo htmlspecialchars($hostel['name']); ?></h4>
                            <p class="text-muted mb-2">
                                <i class="bi bi-geo-alt"></i> 
                                <?php echo htmlspecialchars($hostel['city'] . ', ' . $hostel['state']); ?>
                            </p>
                            <p class="text-muted mb-2">
                                <i class="bi bi-person"></i> 
                                Managed by <?php echo htmlspecialchars($hostel['first_name'] . ' ' . $hostel['last_name']); ?>
                            </p>
                            <h5 class="text-orange mb-0">₦<?php echo number_format($hostel['price_per_month']); ?> / month</h5>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Check-in Date *</label>
                                    <input type="date" name="check_in_date" class="form-control" 
                                           min="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo $_POST['check_in_date'] ?? ''; ?>" required>
                                    <div class="form-text">Select your move-in date</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Duration (Months) *</label>
                                    <select name="duration_months" class="form-select" required>
                                        <option value="">Select duration</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" 
                                                <?php echo ($_POST['duration_months'] ?? '') == $i ? 'selected' : ''; ?>>
                                                <?php echo $i; ?> month<?php echo $i > 1 ? 's' : ''; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="form-text">How long do you want to stay?</div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Calculation -->
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6 class="card-title">Price Summary</h6>
                                <div id="priceSummary">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Monthly rent:</span>
                                        <span>₦<?php echo number_format($hostel['price_per_month']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Duration:</span>
                                        <span id="durationDisplay">0 months</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fw-bold">
                                        <span>Total Amount:</span>
                                        <span id="totalAmount">₦0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="hostel_details.php?id=<?php echo $hostel_id; ?>" class="btn btn-secondary me-md-2">
                                <i class="bi bi-arrow-left"></i> Back to Hostel
                            </a>
                            <button type="submit" class="btn btn-orange">
                                <i class="bi bi-calendar-check"></i> Confirm Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Booking Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Booking Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>What happens next?</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check text-success"></i> Your booking request will be sent to the hostel owner</li>
                                <li><i class="bi bi-check text-success"></i> You'll need to make payment to confirm your booking</li>
                                <li><i class="bi bi-check text-success"></i> Once confirmed, you'll receive booking details</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Important Notes</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-info-circle text-primary"></i> Booking is subject to room availability</li>
                                <li><i class="bi bi-info-circle text-primary"></i> You can cancel within 24 hours for full refund</li>
                                <li><i class="bi bi-info-circle text-primary"></i> Contact hostel owner for special requests</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time price calculation
const pricePerMonth = <?php echo $hostel['price_per_month']; ?>;
const durationSelect = document.querySelector('select[name="duration_months"]');
const durationDisplay = document.getElementById('durationDisplay');
const totalAmount = document.getElementById('totalAmount');

function updatePriceSummary() {
    const duration = parseInt(durationSelect.value) || 0;
    const total = pricePerMonth * duration;
    
    durationDisplay.textContent = duration + ' month' + (duration !== 1 ? 's' : '');
    totalAmount.textContent = '₦' + total.toLocaleString();
}

durationSelect.addEventListener('change', updatePriceSummary);

// Initialize price summary
updatePriceSummary();
</script>

<?php include 'includes/footer.php'; ?>