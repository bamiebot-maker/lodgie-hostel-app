<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('tenant');
$page_title = "My Bookings";
$tenant_id = $_SESSION['user_id'];

// --- Handle "Leave Review" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_review'])) {
    // 1. CSRF Check
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
        redirect('tenant/bookings.php');
    }
    
    // 2. Sanitize
    $booking_id = (int) $_POST['booking_id'];
    $hostel_id = (int) $_POST['hostel_id'];
    $rating = (int) $_POST['rating'];
    $comment = sanitize($_POST['comment']);
    
    // 3. Validate
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error_flash'] = 'Invalid rating. Must be between 1 and 5.';
        redirect('tenant/bookings.php');
    }
    
    // 4. Check if they are allowed to review (e.g., booking is completed)
    // For this demo, we'll allow review on 'paid' or 'confirmed'
    $stmt_check = $pdo->prepare("
        SELECT id FROM bookings 
        WHERE id = ? AND tenant_id = ? AND (status = 'paid' OR status = 'confirmed' OR status = 'completed')
    ");
    $stmt_check->execute([$booking_id, $tenant_id]);
    if (!$stmt_check->fetch()) {
        $_SESSION['error_flash'] = 'You cannot review this booking.';
        redirect('tenant/bookings.php');
    }
    
    // 5. Insert review (or update if already exists)
    try {
        $sql_review = "
            INSERT INTO reviews (booking_id, tenant_id, hostel_id, rating, comment)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                rating = VALUES(rating), 
                comment = VALUES(comment)
        ";
        $stmt_review = $pdo->prepare($sql_review);
        $stmt_review->execute([$booking_id, $tenant_id, $hostel_id, $rating, $comment]);
        
        $_SESSION['success_flash'] = 'Thank you for your review!';
        redirect('tenant/bookings.php');
        
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Failed to submit review. You may have already reviewed this booking.';
        error_log('Review submission error: ' . $e->getMessage());
        redirect('tenant/bookings.php');
    }
}

// --- Fetch All Bookings for this Tenant ---
$stmt = $pdo->prepare("
    SELECT 
        b.*, 
        h.name as hostel_name,
        (SELECT image_path FROM hostel_images hi WHERE hi.hostel_id = h.id ORDER BY hi.is_thumbnail DESC, hi.id ASC LIMIT 1) as thumbnail,
        (SELECT id FROM reviews r WHERE r.booking_id = b.id AND r.tenant_id = b.tenant_id) as review_id
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    WHERE b.tenant_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$tenant_id]);
$bookings = $stmt->fetchAll();

$csrf_token = generate_csrf_token();
$highlight_id = (int)($_GET['highlight'] ?? 0); // For new bookings

// --- Header ---
get_header($pdo); 
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">My Bookings</h1>

    <?php display_flash_messages(); ?>

    <?php if (empty($bookings)): ?>
        <div class="card">
            <div class="card-body text-center p-5">
                <i class="bi bi-calendar-x" style="font-size: 4rem;"></i>
                <h4 class="mt-3">You have no bookings yet.</h4>
                <p>Start by finding your perfect hostel!</p>
                <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-orange">Browse Hostels</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
            <?php
            // Determine card border color based on status
            $border_class = '';
            if ($booking['id'] === $highlight_id) {
                $border_class = 'border-primary border-3';
            } elseif ($booking['status'] === 'pending') {
                $border_class = 'border-warning';
            } elseif ($booking['status'] === 'paid' || $booking['status'] === 'confirmed') {
                $border_class = 'border-success';
            }
            ?>
            <div class="card shadow-sm mb-4 <?php echo $border_class; ?>">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <img src="<?php echo BASE_URL . '/uploads/hostels/' . ($booking['thumbnail'] ? sanitize($booking['thumbnail']) : 'hostel_placeholder.jpg'); ?>" class="img-fluid rounded-lg" alt="<?php echo sanitize($booking['hostel_name']); ?>" style="height: 150px; width: 100%; object-fit: cover;">
                        </div>
                        
                        <div class="col-md-6">
                            <h4 class="mb-1"><?php echo sanitize($booking['hostel_name']); ?></h4>
                            <p class="text-muted mb-2">Booking ID: #<?php echo $booking['id']; ?></p>
                            <ul class="list-unstyled">
                                <li><strong>Move-in:</strong> <?php echo date('M j, Y', strtotime($booking['start_date'])); ?></li>
                                <li><strong>Duration:</strong> <?php echo $booking['duration_months']; ?> month(s)</li>
                                <li><strong>Total Price:</strong> <span class="fw-bold text-orange"><?php echo format_price($booking['total_price']); ?></span></li>
                            </ul>
                        </div>
                        
                        <div class="col-md-3 text-md-end">
                            <h6 class="mb-1">Status:</h6>
                            <span class="badge fs-6 bg-<?php 
                                $status_map = ['pending' => 'warning', 'paid' => 'success', 'confirmed' => 'primary', 'cancelled' => 'danger', 'completed' => 'secondary'];
                                echo $status_map[$booking['status']] ?? 'light'; 
                            ?>">
                                <?php echo ucfirst(sanitize($booking['status'])); ?>
                            </span>
                            
                            <div class="mt-3">
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <form action="<?php echo BASE_URL; ?>/payments/paystack_init.php" method="POST">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="bi bi-credit-card-fill me-1"></i> Pay Now
                                        </button>
                                    </form>
                                    <form action="<?php echo BASE_URL; ?>/tenant/cancel_booking.php" method="POST" class="mt-2" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Cancel</button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if (in_array($booking['status'], ['paid', 'confirmed', 'completed'])): ?>
                                    <button class="btn btn-outline-orange w-100" data-bs-toggle="modal" data-bs-target="#reviewModal-<?php echo $booking['id']; ?>">
                                        <?php echo $booking['review_id'] ? 'Edit Review' : 'Leave Review'; ?>
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>/tenant/receipt.php?id=<?php echo $booking['id']; ?>" class="btn btn-outline-secondary w-100 mt-2" target="_blank">
                                        View Receipt
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="reviewModal-<?php echo $booking['id']; ?>" tabindex="-1" aria-labelledby="reviewModalLabel-<?php echo $booking['id']; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <form action="bookings.php" method="POST">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="reviewModalLabel-<?php echo $booking['id']; ?>">Review <?php echo sanitize($booking['hostel_name']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                <input type="hidden" name="hostel_id" value="<?php echo $booking['hostel_id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Rating</label>
                                    <select class="form-select" name="rating" required>
                                        <option value="">Select a rating</option>
                                        <option value="5">5 Stars (Excellent)</option>
                                        <option value="4">4 Stars (Good)</option>
                                        <option value="3">3 Stars (Average)</option>
                                        <option value="2">2 Stars (Poor)</option>
                                        <option value="1">1 Star (Terrible)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="comment-<?php echo $booking['id']; ?>" class="form-label">Comment (Optional)</label>
                                    <textarea class="form-control" id="comment-<?php echo $booking['id']; ?>" name="comment" rows="4" placeholder="Share your experience..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" name="leave_review" class="btn btn-orange">Submit Review</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// --- Footer ---
get_footer(); 
?>