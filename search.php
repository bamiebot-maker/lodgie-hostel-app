<?php
$page_title = "Search Hostels - Lodgie";
require_once 'includes/header-public.php';

// Get search parameters
$search = $_GET['q'] ?? '';
$city = $_GET['city'] ?? '';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$room_type = $_GET['room_type'] ?? '';

// Build query conditions
$conditions = ["h.is_published = 1", "h.is_verified = 1", "h.available_rooms > 0"];
$params = [];

if (!empty($search)) {
    $conditions[] = "(h.title LIKE ? OR h.description LIKE ? OR h.address LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

if (!empty($city)) {
    $conditions[] = "h.city LIKE ?";
    $params[] = "%$city%";
}

if (!empty($price_min)) {
    $conditions[] = "h.price_per_month >= ?";
    $params[] = $price_min;
}

if (!empty($price_max)) {
    $conditions[] = "h.price_per_month <= ?";
    $params[] = $price_max;
}

if (!empty($room_type) && $room_type !== 'all') {
    $conditions[] = "h.room_type = ?";
    $params[] = $room_type;
}

$where_sql = implode(" AND ", $conditions);

// Pagination
$per_page = 12;
$page = max(1, $_GET['page'] ?? 1);
$offset = ($page - 1) * $per_page;

// Get total count
$total_count = $db->fetch(
    "SELECT COUNT(*) as count FROM hostels h WHERE $where_sql",
    $params
)['count'];

$total_pages = ceil($total_count / $per_page);

// Get hostels
$hostels = $db->fetchAll(
    "SELECT h.*, u.full_name as landlord_name,
     (SELECT AVG(rating) FROM reviews WHERE hostel_id = h.id) as avg_rating,
     (SELECT COUNT(*) FROM reviews WHERE hostel_id = h.id) as review_count,
     (SELECT filename FROM hostel_images WHERE hostel_id = h.id AND is_cover = 1 LIMIT 1) as cover_image
     FROM hostels h 
     JOIN users u ON h.landlord_id = u.id 
     WHERE $where_sql 
     ORDER BY h.created_at DESC 
     LIMIT $per_page OFFSET $offset",
    $params
);
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="h2">Find Your Perfect Hostel</h1>
        <p class="text-muted">Browse through our verified hostel listings</p>
    </div>
</div>

<!-- Search Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="q" value="<?php echo sanitize_input($search); ?>" placeholder="Hostel name or location">
            </div>
            <div class="col-md-2">
                <label class="form-label">City</label>
                <input type="text" class="form-control" name="city" value="<?php echo sanitize_input($city); ?>" placeholder="Any city">
            </div>
            <div class="col-md-2">
                <label class="form-label">Min Price</label>
                <input type="number" class="form-control" name="price_min" value="<?php echo sanitize_input($price_min); ?>" placeholder="₦0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Max Price</label>
                <input type="number" class="form-control" name="price_max" value="<?php echo sanitize_input($price_max); ?>" placeholder="₦100,000">
            </div>
            <div class="col-md-2">
                <label class="form-label">Room Type</label>
                <select class="form-select" name="room_type">
                    <option value="all">All Types</option>
                    <option value="shared" <?php echo $room_type === 'shared' ? 'selected' : ''; ?>>Shared</option>
                    <option value="single" <?php echo $room_type === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="self-contained" <?php echo $room_type === 'self-contained' ? 'selected' : ''; ?>>Self-Contained</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Results Info -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">
        <?php echo $total_count; ?> hostel<?php echo $total_count != 1 ? 's' : ''; ?> found
    </p>
</div>

<!-- Hostels Grid -->
<?php if (!empty($hostels)): ?>
    <div class="row g-4">
        <?php foreach ($hostels as $hostel): ?>
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card hostel-card h-100">
                <?php if ($hostel['cover_image']): ?>
                    <img src="uploads/hostels/<?php echo $hostel['cover_image']; ?>" 
                         class="card-img-top" alt="<?php echo sanitize_input($hostel['title']); ?>">
                <?php else: ?>
                    <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="fas fa-home fa-3x text-white"></i>
                    </div>
                <?php endif; ?>
                
                <div class="card-body d-flex flex-column">
                    <h6 class="card-title"><?php echo sanitize_input($hostel['title']); ?></h6>
                    
                    <p class="card-text text-muted small mb-2">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <?php echo sanitize_input($hostel['city'] . ', ' . $hostel['state']); ?>
                    </p>
                    
                    <p class="card-text small text-muted mb-2">
                        <i class="fas fa-door-open me-1"></i>
                        <?php echo ucfirst(str_replace('-', ' ', $hostel['room_type'])); ?> · 
                        <?php echo $hostel['available_rooms']; ?> available
                    </p>

                    <?php if ($hostel['avg_rating']): ?>
                        <div class="rating-stars small mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= round($hostel['avg_rating']) ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                            <small class="text-muted ms-1">(<?php echo $hostel['review_count']; ?>)</small>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small mb-2">No reviews yet</div>
                    <?php endif; ?>

                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="price">₦<?php echo number_format($hostel['price_per_month'], 2); ?></span>
                            <a href="hostels/view.php?id=<?php echo $hostel['id']; ?>" class="btn btn-primary btn-sm">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            Previous
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-search fa-3x text-muted mb-3"></i>
        <h4 class="text-muted">No hostels found</h4>
        <p class="text-muted">Try adjusting your search criteria</p>
        <a href="search.php" class="btn btn-primary">Clear Filters</a>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>