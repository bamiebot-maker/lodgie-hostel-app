<?php
$page_title = "Hostel Details - Lodgie";
require_once '../includes/header-public.php'; // Use public header since anyone can view hostels

$hostel_id = $_GET['id'] ?? 0;

if (!$hostel_id) {
    redirect('../index.php');
}

// Get hostel details - $db is now available from header-public.php
$hostel = $db->fetch(
    "SELECT h.*, u.full_name as landlord_name, u.phone as landlord_phone, u.email as landlord_email,
     (SELECT AVG(rating) FROM reviews WHERE hostel_id = h.id) as avg_rating,
     (SELECT COUNT(*) FROM reviews WHERE hostel_id = h.id) as review_count
     FROM hostels h 
     JOIN users u ON h.landlord_id = u.id 
     WHERE h.id = ? AND h.is_published = 1 AND h.is_verified = 1",
    [$hostel_id]
);

if (!$hostel) {
    $_SESSION['message'] = 'Hostel not found or not available';
    $_SESSION['message_type'] = 'danger';
    redirect('../search.php');
}

// Get hostel images
$images = $db->fetchAll(
    "SELECT * FROM hostel_images WHERE hostel_id = ? ORDER BY is_cover DESC",
    [$hostel_id]
);

// Get reviews
$reviews = $db->fetchAll(
    "SELECT r.*, u.full_name 
     FROM reviews r 
     JOIN users u ON r.user_id = u.id 
     WHERE r.hostel_id = ? 
     ORDER BY r.created_at DESC 
     LIMIT 10",
    [$hostel_id]
);

// Update page title with hostel name
$page_title = $hostel['title'] . " - Lodgie";
?>

<!-- Rest of the view.php content remains the same -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="../search.php">Hostels</a></li>
        <li class="breadcrumb-item active"><?php echo sanitize_input($hostel['title']); ?></li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <!-- Image Gallery -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <?php if (!empty($images)): ?>
                    <div id="hostelCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($images as $index => $image): ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="../uploads/hostels/<?php echo $image['filename']; ?>" 
                                         class="d-block w-100" style="height: 400px; object-fit: cover;" 
                                         alt="<?php echo sanitize_input($hostel['title']); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($images) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#hostelCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#hostelCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 bg-light">
                        <i class="fas fa-home fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No images available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rest of the hostel view content... -->
        <!-- [Keep all the existing content from your view.php file] -->
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>