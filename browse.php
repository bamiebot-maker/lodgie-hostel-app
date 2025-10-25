<?php
// --- Core Includes ---
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// --- Page-Specific Logic ---
$page_title = "Browse Hostels";

// --- Filtering & Searching ---
$search_term = sanitize($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$limit = 9;
$offset = ($page - 1) * $limit;

// Base query
$sql = "SELECT h.*, 
           (SELECT image_path FROM hostel_images hi WHERE hi.hostel_id = h.id ORDER BY hi.is_thumbnail DESC, hi.id ASC LIMIT 1) as thumbnail,
           (SELECT AVG(r.rating) FROM reviews r WHERE r.hostel_id = h.id) as avg_rating
        FROM hostels h
        WHERE h.status = 'approved'";

$params = [];
$count_params = []; // Separate params for the count query

// Add search condition
if (!empty($search_term)) {
    $sql .= " AND (h.name LIKE ? OR h.city LIKE ? OR h.address LIKE ?)";
    $search_param = "%$search_term%";
    // Add to both param arrays
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

// --- Pagination ---
// Get total count for pagination
// **FIX:** Use a simplified count query
$count_sql = "SELECT COUNT(h.id) FROM hostels h WHERE h.status = 'approved'";
if (!empty($search_term)) {
     $count_sql .= " AND (h.name LIKE ? OR h.city LIKE ? OR h.address LIKE ?)";
}

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($count_params); // Use the count_params
$total_hostels = $stmt_count->fetchColumn();
$total_pages = ceil($total_hostels / $limit);

// **FIX:** Change named parameters to positional (?)
$sql .= " ORDER BY h.created_at DESC LIMIT ? OFFSET ?";

// --- Fetch Hostels ---
$stmt = $pdo->prepare($sql);

// **FIX:** Bind all parameters positionally
$param_index = 1;
// Bind search params (if any)
foreach ($params as $param) {
    $stmt->bindValue($param_index++, $param, PDO::PARAM_STR);
}

// Bind pagination params as integers
$stmt->bindValue($param_index++, (int) $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, (int) $offset, PDO::PARAM_INT);

$stmt->execute();
$hostels = $stmt->fetchAll();


// --- Header ---
// **FIX:** Pass $pdo to the header
get_header($pdo); 
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Search & Filter</h5>
                </div>
                <div class="card-body">
                    <form action="browse.php" method="GET">
                        <div class="mb-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo sanitize($search_term); ?>" placeholder="Hostel name, city...">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-orange">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 mb-3">
                <?php echo empty($search_term) ? 'All Hostels' : 'Search Results for "' . sanitize($search_term) . '"'; ?>
            </h1>
            <p class="text-muted">Showing <?php echo $stmt->rowCount(); ?> of <?php echo $total_hostels; ?> results.</p>
            
            <hr>
            
            <div class="row g-4">
                <?php if (empty($hostels)): ?>
                    <div class="col-12">
                        <div class="alert alert-warning text-center">
                            No hostels found matching your criteria.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($hostels as $hostel): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card hostel-card h-100">
                                <a href="hostel_details.php?id=<?php echo $hostel['id']; ?>">
                                    <img src="<?php echo BASE_URL . '/uploads/hostels/' . ($hostel['thumbnail'] ? sanitize($hostel['thumbnail']) : 'hostel_placeholder.jpg'); ?>" class="card-img-top" alt="<?php echo sanitize($hostel['name']); ?>">
                                </a>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo sanitize($hostel['name']); ?></h5>
                                    <p class="card-text text-muted mb-2"><i class="bi bi-geo-alt-fill me-1"></i> <?php echo sanitize($hostel['city']); ?></p>
                                    
                                    <div class="mb-2">
                                        <?php $rating = (float) $hostel['avg_rating']; ?>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?php echo $i <= $rating ? 'bi-star-fill text-warning' : ($i - 0.5 <= $rating ? 'bi-star-half text-warning' : 'bi-star text-warning'); ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted small">(<?php echo number_format($rating, 1); ?>)</span>
                                    </div>

                                    <div class="mt-auto d-flex justify-content-between align-items-center">
                                        <div class="price">
                                            <?php echo format_price($hostel['price_per_month']); ?>
                                            <span class="duration">/ month</span>
                                        </div>
                                        <a href="hostel_details.php?id=<?php echo $hostel['id']; ?>" class="btn btn-sm btn-outline-orange">View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <nav aria-label="Page navigation" class="mt-5">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>">&raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

        </div>
    </div>
</div>

<?php
// --- Footer ---
get_footer(); 
?>