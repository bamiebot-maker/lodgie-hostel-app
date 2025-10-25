<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

Auth::requireRole(['admin']);
$page_title = "Manage Reviews";
require_once '../includes/headers/header_admin.php';

$db = new Database();

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $review_id = intval($_GET['id']);
    
    try {
        switch ($_GET['action']) {
            case 'approve':
                $db->query("UPDATE reviews SET is_approved = TRUE WHERE id = ?", [$review_id]);
                $_SESSION['success'] = "Review approved successfully";
                break;
                
            case 'reject':
                $db->query("UPDATE reviews SET is_approved = FALSE WHERE id = ?", [$review_id]);
                $_SESSION['success'] = "Review rejected successfully";
                break;
                
            case 'delete':
                $db->query("DELETE FROM reviews WHERE id = ?", [$review_id]);
                $_SESSION['success'] = "Review deleted successfully";
                break;
        }
        
        header('Location: reviews.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT r.*, 
               u.first_name, u.last_name, u.email as tenant_email,
               h.name as hostel_name, h.city, h.state,
               b.check_in_date, b.check_out_date
        FROM reviews r
        JOIN users u ON r.tenant_id = u.id
        JOIN hostels h ON r.hostel_id = h.id
        JOIN bookings b ON r.booking_id = b.id
        WHERE 1=1";
$params = [];

if ($status_filter === 'approved') {
    $sql .= " AND r.is_approved = TRUE";
} elseif ($status_filter === 'pending') {
    $sql .= " AND r.is_approved = FALSE";
}

if ($rating_filter) {
    $sql .= " AND r.rating = ?";
    $params[] = $rating_filter;
}

if ($search) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR h.name LIKE ? OR r.comment LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY r.created_at DESC";

// Get reviews
$stmt = $db->query($sql, $params);
$reviews = $stmt->fetchAll();

// Get review statistics
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_reviews,
        AVG(rating) as avg_rating,
        SUM(is_approved = TRUE) as approved_reviews,
        SUM(is_approved = FALSE) as pending_reviews,
        COUNT(DISTINCT hostel_id) as hostels_reviewed,
        COUNT(DISTINCT tenant_id) as unique_reviewers
    FROM reviews
");
$review_stats = $stmt->fetch();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <h1 class="h2">Manage Reviews</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <span class="badge bg-warning me-2"><?php echo $review_stats['pending_reviews']; ?> Pending</span>
        <span class="badge bg-success"><?php echo $review_stats['approved_reviews']; ?> Approved</span>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- Review Statistics -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo $review_stats['total_reviews']; ?></h4>
                <small>Total Reviews</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($review_stats['avg_rating'], 1); ?></h4>
                <small>Avg Rating</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo $review_stats['approved_reviews']; ?></h4>
                <small>Approved</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo $review_stats['pending_reviews']; ?></h4>
                <small>Pending</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo $review_stats['hostels_reviewed']; ?></h4>
                <small>Hostels</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-dark text-white">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo $review_stats['unique_reviewers']; ?></h4>
                <small>Reviewers</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Approval Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select">
                    <option value="">All Ratings</option>
                    <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                    <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                    <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                    <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                    <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search reviews, tenants, or hostels..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-orange w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Reviews List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Reviews List</h5>
        <span class="badge bg-orange"><?php echo count($reviews); ?> reviews</span>
    </div>
    <div class="card-body">
        <?php if (empty($reviews)): ?>
            <div class="text-center py-4">
                <i class="bi bi-star text-muted fs-1"></i>
                <p class="text-muted mt-2">No reviews found</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($reviews as $review): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Rating Stars -->
                                    <div class="mb-2">
                                        <span class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?> fs-5"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <span class="text-muted ms-2">(<?php echo $review['rating']; ?>/5)</span>
                                    </div>
                                    
                                    <!-- Review Comment -->
                                    <p class="mb-3">"<?php echo htmlspecialchars($review['comment']); ?>"</p>
                                    
                                    <!-- Reviewer Info -->
                                    <div class="d-flex align-items-center mb-2">
                                        <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                        <span class="text-muted mx-2">•</span>
                                        <small class="text-muted"><?php echo htmlspecialchars($review['tenant_email']); ?></small>
                                    </div>
                                    
                                    <!-- Hostel Info -->
                                    <div class="mb-2">
                                        <i class="bi bi-house text-muted"></i>
                                        <strong><?php echo htmlspecialchars($review['hostel_name']); ?></strong>
                                        <span class="text-muted ms-2"><?php echo htmlspecialchars($review['city'] . ', ' . $review['state']); ?></span>
                                    </div>
                                    
                                    <!-- Stay Period -->
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar"></i>
                                        Stayed: <?php echo format_date($review['check_in_date']); ?> - <?php echo format_date($review['check_out_date']); ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="d-flex flex-column h-100">
                                        <!-- Review Meta -->
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Reviewed on</small>
                                            <strong><?php echo format_date($review['created_at']); ?></strong>
                                        </div>
                                        
                                        <!-- Approval Status -->
                                        <div class="mb-3">
                                            <span class="badge bg-<?php echo $review['is_approved'] ? 'success' : 'warning'; ?>">
                                                <?php echo $review['is_approved'] ? 'Approved' : 'Pending Approval'; ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="mt-auto">
                                            <div class="btn-group w-100">
                                                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if (!$review['is_approved']): ?>
                                                        <li><a class="dropdown-item text-success" href="?action=approve&id=<?php echo $review['id']; ?>" onclick="return confirm('Approve this review?')">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </a></li>
                                                    <?php else: ?>
                                                        <li><a class="dropdown-item text-warning" href="?action=reject&id=<?php echo $review['id']; ?>" onclick="return confirm('Reject this review?')">
                                                            <i class="bi bi-x-circle"></i> Reject
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item text-info" href="../hostel_details.php?id=<?php echo $review['hostel_id']; ?>" target="_blank">
                                                        <i class="bi bi-eye"></i> View Hostel
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $review['id']; ?>" onclick="return confirm('Are you sure you want to delete this review? This action cannot be undone.')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>