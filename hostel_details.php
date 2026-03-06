<?php
// --- Core Includes ---
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/notifications.php'; // For booking logic

// --- Page-Specific Logic ---
$hostel_id = (int)($_GET['id'] ?? 0);
if ($hostel_id === 0) {
    $_SESSION['error_flash'] = 'Invalid hostel ID.';
    redirect('browse.php');
}

// Fetch hostel details
$stmt_hostel = $pdo->prepare("
    SELECT h.*, u.name as landlord_name,
           (SELECT AVG(r.rating) FROM reviews r WHERE r.hostel_id = h.id) as avg_rating,
           (SELECT COUNT(r.id) FROM reviews r WHERE r.hostel_id = h.id) as review_count
    FROM hostels h
    JOIN users u ON h.landlord_id = u.id
    WHERE h.id = ? AND h.status = 'approved'
");
$stmt_hostel->execute([$hostel_id]);
$hostel = $stmt_hostel->fetch();

if (!$hostel) {
    $_SESSION['error_flash'] = 'Hostel not found or is not approved.';
    redirect('browse.php');
}

// Fetch hostel images
$stmt_images = $pdo->prepare("SELECT image_path FROM hostel_images WHERE hostel_id = ? ORDER BY is_thumbnail DESC, id ASC");
$stmt_images->execute([$hostel_id]);
$images = $stmt_images->fetchAll(PDO::FETCH_COLUMN);
if (empty($images)) {
    $images[] = 'hostel_placeholder.jpg';
}

// Fetch reviews
$stmt_reviews = $pdo->prepare("
    SELECT r.*, u.name as tenant_name 
    FROM reviews r
    JOIN users u ON r.tenant_id = u.id
    WHERE r.hostel_id = ? 
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt_reviews->execute([$hostel_id]);
$reviews = $stmt_reviews->fetchAll();

// Get amenities (assuming comma-separated string)
$amenities = !empty($hostel['amenities']) ? explode(',', $hostel['amenities']) : [];

$page_title = sanitize($hostel['name']);

// --- Handle Booking Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_now'])) {
    
    // 1. Check if user is a tenant
    if (!check_role('tenant')) {
        $_SESSION['error_flash'] = 'You must be logged in as a tenant to book.';
        redirect('login.php');
    }
    
    // 2. Verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request. Please try again.';
        redirect('hostel_details.php?id=' . $hostel_id);
    }
    
    // 3. Sanitize inputs
    $start_date = sanitize($_POST['start_date']);
    $duration_months = (int) sanitize($_POST['duration_months']);
    $tenant_id = (int) $_SESSION['user_id'];
    $landlord_id = (int) $hostel['landlord_id'];
    
    // 4. Validate inputs
    if (empty($start_date) || $duration_months <= 0) {
        $_SESSION['error_flash'] = 'Please select a valid start date and duration.';
        redirect('hostel_details.php?id=' . $hostel_id);
    }
    // Check if start date is in the past
    if (strtotime($start_date) < strtotime('today')) {
        $_SESSION['error_flash'] = 'Start date cannot be in the past.';
        redirect('hostel_details.php?id=' . $hostel_id);
    }
    
    // 5. Calculate price
    $total_price = (float) $hostel['price_per_month'] * $duration_months;
    
    try {
        // 5.5 Prevent duplicate pending bookings for same hostel
        $stmt_dup = $pdo->prepare("SELECT id FROM bookings WHERE tenant_id = ? AND hostel_id = ? AND status = 'pending' LIMIT 1");
        $stmt_dup->execute([$tenant_id, $hostel_id]);
        if ($stmt_dup->fetch()) {
            $_SESSION['error_flash'] = 'You already have a pending booking for this hostel. Please pay or cancel it first.';
            redirect('tenant/bookings.php');
        }

        // 6. Create 'pending' booking record
        $sql_book = "
            INSERT INTO bookings (tenant_id, hostel_id, landlord_id, start_date, duration_months, total_price, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ";
        $stmt_book = $pdo->prepare($sql_book);
        $stmt_book->execute([
            $tenant_id,
            $hostel_id,
            $landlord_id,
            $start_date,
            $duration_months,
            $total_price
        ]);
        
        $booking_id = $pdo->lastInsertId();
        
        // 7. Redirect to payment initialization
        // We will pass the new booking_id to paystack_init.php
        // We can do this via a session flash or by auto-submitting a form.
        // Let's create a form and auto-submit it with JavaScript.
        
        $_SESSION['booking_to_pay'] = $booking_id;
        $_SESSION['success_flash'] = 'Booking created! Redirecting to payment...';
        
        // This is a simple redirect to a confirmation page that
        // will then contain the "Pay Now" button leading to paystack_init.php
        redirect('tenant/bookings.php?highlight=' . $booking_id);
        
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Failed to create booking. Please try again.';
        error_log('Booking creation error: ' . $e->getMessage());
        redirect('hostel_details.php?id=' . $hostel_id);
    }
}

$csrf_token = generate_csrf_token();

// --- Header ---
get_header($pdo); 
?>

<div class="container py-5">
    
    <?php display_flash_messages(); ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div id="hostelCarousel" class="carousel slide card" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <?php foreach ($images as $i => $image): ?>
                        <button type="button" data-bs-target="#hostelCarousel" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>" aria-current="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $i + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner rounded-lg">
                    <?php foreach ($images as $i => $image): ?>
                        <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                            <img src="<?php echo BASE_URL . '/uploads/hostels/' . sanitize($image); ?>" class="d-block w-100" alt="Hostel Image <?php echo $i + 1; ?>" style="height: 450px; object-fit: cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#hostelCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#hostelCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>

            <div class="card mt-4">
                <div class="card-body p-4">
                    <h1 class="h2 mb-3"><?php echo sanitize($hostel['name']); ?></h1>
                    <p class="text-muted"><i class="bi bi-geo-alt-fill me-1"></i> <?php echo sanitize($hostel['address']); ?>, <?php echo sanitize($hostel['city']); ?></p>
                    
                    <div class="mb-3">
                        <?php $rating = (float) $hostel['avg_rating']; ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi <?php echo $i <= $rating ? 'bi-star-fill text-warning' : ($i - 0.5 <= $rating ? 'bi-star-half text-warning' : 'bi-star text-warning'); ?>"></i>
                        <?php endfor; ?>
                        <span class="text-muted ms-2"><?php echo number_format($rating, 1); ?> (<?php echo $hostel['review_count']; ?> reviews)</span>
                    </div>

                    <p class="text-muted">Listed by: <span class="fw-bold"><?php echo sanitize($hostel['landlord_name']); ?></span></p>

                    <hr>

                    <h4 class="mb-3">Description</h4>
                    <p><?php echo nl2br(sanitize($hostel['description'])); ?></p>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body p-4">
                    <h4 class="mb-3">Amenities</h4>
                    <?php if (empty($amenities)): ?>
                        <p class="text-muted">No amenities listed.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($amenities as $amenity): ?>
                                <div class="col-md-6 col-lg-4 mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i> <?php echo sanitize(trim($amenity)); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body p-4">
                    <h4 class="mb-3">Reviews (<?php echo $hostel['review_count']; ?>)</h4>
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted">Be the first to review this hostel!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-center mb-2">
                                    <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" class="rounded-circle" width="40" height="40" alt="Avatar">
                                    <div class="ms-2">
                                        <h6 class="mb-0"><?php echo sanitize($review['tenant_name']); ?></h6>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></small>
                                    </div>
                                </div>
                                <div class="ratings text-warning mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi <?php echo $i <= $review['rating'] ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mb-0"><?php echo nl2br(sanitize($review['comment'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 80px;">
                <div class="card-body p-4">
                    <h3 class="mb-0"><?php echo format_price($hostel['price_per_month']); ?></h3>
                    <p class="text-muted mb-3">/ per month</p>
                    
                    <hr>
                    
                    <?php if (check_role('tenant')): ?>
                        <form action="hostel_details.php?id=<?php echo $hostel_id; ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label for="start_date" class="form-label fw-bold">Move-in Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="duration_months" class="form-label fw-bold">Duration (in months)</label>
                                <input type="number" class="form-control" id="duration_months" name="duration_months" value="1" min="1" max="12" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="book_now" class="btn btn-orange btn-lg">Book Now</button>
                            </div>
                        </form>
                    <?php elseif (check_role('landlord')): ?>
                        <div class="alert alert-info">
                            You are logged in as a landlord. <a href="<?php echo BASE_URL; ?>/logout.php">Log out</a> and register as a tenant to book.
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-orange btn-lg">Login to Book</a>
                            <a href="<?php echo BASE_URL; ?>/register.php?role=tenant" class="btn btn-outline-orange">Register as Tenant</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// --- Footer ---
get_footer(); 
?>