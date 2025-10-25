<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

Auth::requireRole(['admin']);
$page_title = "User Details";
require_once '../includes/headers/header_admin.php';

$db = new Database();

// Get user data
$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) {
    header('Location: users.php');
    exit;
}

$stmt = $db->query("SELECT * FROM users WHERE id = ?", [$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "User not found";
    header('Location: users.php');
    exit;
}

// Get user statistics based on role
$stats = [];
$related_data = [];

if ($user['role'] === 'landlord') {
    // Landlord statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_hostels,
            SUM(is_approved = TRUE) as approved_hostels,
            SUM(is_approved = FALSE) as pending_hostels,
            SUM(is_active = TRUE) as active_hostels,
            AVG(price_per_month) as avg_price
        FROM hostels 
        WHERE landlord_id = ?
    ", [$user_id]);
    $stats = $stmt->fetch();
    
    // Get landlord's hostels
    $stmt = $db->query("
        SELECT h.*, 
               (SELECT image_path FROM hostel_images WHERE hostel_id = h.id AND is_primary = TRUE LIMIT 1) as primary_image,
               (SELECT AVG(rating) FROM reviews WHERE hostel_id = h.id) as avg_rating,
               (SELECT COUNT(*) FROM bookings WHERE hostel_id = h.id) as booking_count
        FROM hostels h
        WHERE h.landlord_id = ?
        ORDER BY h.created_at DESC
        LIMIT 10
    ", [$user_id]);
    $related_data = $stmt->fetchAll();
    
} elseif ($user['role'] === 'tenant') {
    // Tenant statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(status = 'confirmed') as confirmed_bookings,
            SUM(status = 'pending') as pending_bookings,
            SUM(status = 'cancelled') as cancelled_bookings,
            SUM(total_amount) as total_spent
        FROM bookings 
        WHERE tenant_id = ?
    ", [$user_id]);
    $stats = $stmt->fetch();
    
    // Get tenant's bookings
    $stmt = $db->query("
        SELECT b.*, h.name as hostel_name, h.city, h.state,
               (SELECT image_path FROM hostel_images WHERE hostel_id = h.id AND is_primary = TRUE LIMIT 1) as hostel_image,
               p.payment_status, p.amount as paid_amount
        FROM bookings b
        JOIN hostels h ON b.hostel_id = h.id
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE b.tenant_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ", [$user_id]);
    $related_data = $stmt->fetchAll();
    
    // Get tenant's reviews
    $stmt = $db->query("
        SELECT r.*, h.name as hostel_name
        FROM reviews r
        JOIN hostels h ON r.hostel_id = h.id
        WHERE r.tenant_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ", [$user_id]);
    $reviews = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <h1 class="h2">User Details</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="users.php" class="btn btn-outline-orange me-2">
            <i class="bi bi-arrow-left"></i> Back to Users
        </a>
        <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-orange">
            <i class="bi bi-pencil"></i> Edit User
        </a>
    </div>
</div>

<div class="row">
    <!-- User Profile -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <img src="../uploads/avatars/<?php echo $user['avatar']; ?>" 
                     class="rounded-circle mb-3" width="120" height="120" 
                     style="object-fit: cover;" 
                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>&background=f97316&color=fff'">
                
                <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                
                <span class="badge bg-<?php 
                    switch($user['role']) {
                        case 'admin': echo 'danger'; break;
                        case 'landlord': echo 'warning'; break;
                        case 'tenant': echo 'info'; break;
                        default: echo 'secondary';
                    }
                ?> fs-6 mb-2"><?php echo get_role_display($user['role']); ?></span>
                
                <div class="mt-3">
                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Contact Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                        <?php echo htmlspecialchars($user['email']); ?>
                    </a>
                </div>
                
                <?php if ($user['phone']): ?>
                <div class="mb-3">
                    <strong>Phone:</strong><br>
                    <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>">
                        <?php echo htmlspecialchars($user['phone']); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Member Since:</strong><br>
                    <?php echo format_date($user['created_at'], 'F j, Y'); ?>
                </div>
                
                <div class="mb-0">
                    <strong>Last Updated:</strong><br>
                    <?php echo format_date($user['updated_at'], 'F j, Y'); ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </a>
                    
                    <?php if ($user['is_active']): ?>
                        <a href="?action=deactivate&id=<?php echo $user['id']; ?>" class="btn btn-outline-warning" 
                           onclick="return confirm('Deactivate this user?')">
                            <i class="bi bi-pause-circle"></i> Deactivate
                        </a>
                    <?php else: ?>
                        <a href="?action=activate&id=<?php echo $user['id']; ?>" class="btn btn-outline-success" 
                           onclick="return confirm('Activate this user?')">
                            <i class="bi bi-play-circle"></i> Activate
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] !== 'admin'): ?>
                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-outline-danger" 
                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                            <i class="bi bi-trash"></i> Delete User
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Statistics & Activity -->
    <div class="col-md-8">
        <!-- Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <?php echo get_role_display($user['role']); ?> Statistics
                </h5>
            </div>
            <div class="card-body">
                <?php if ($user['role'] === 'landlord' && !empty($stats)): ?>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-primary"><?php echo $stats['total_hostels']; ?></h3>
                                <small class="text-muted">Total Hostels</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-success"><?php echo $stats['approved_hostels']; ?></h3>
                                <small class="text-muted">Approved</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-warning"><?php echo $stats['pending_hostels']; ?></h3>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-info">₦<?php echo number_format($stats['avg_price'] ?? 0); ?></h3>
                                <small class="text-muted">Avg Price</small>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($user['role'] === 'tenant' && !empty($stats)): ?>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-primary"><?php echo $stats['total_bookings']; ?></h3>
                                <small class="text-muted">Total Bookings</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-success"><?php echo $stats['confirmed_bookings']; ?></h3>
                                <small class="text-muted">Confirmed</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-warning"><?php echo $stats['pending_bookings']; ?></h3>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <h3 class="text-info">₦<?php echo number_format($stats['total_spent'] ?? 0); ?></h3>
                                <small class="text-muted">Total Spent</small>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($user['role'] === 'admin'): ?>
                    <div class="text-center py-3">
                        <i class="bi bi-shield-check text-muted fs-1"></i>
                        <p class="text-muted mt-2">Administrator account with full system access</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <p class="text-muted">No activity data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Recent Activity
                    <?php if ($user['role'] === 'landlord'): ?>
                        - Hostels
                    <?php elseif ($user['role'] === 'tenant'): ?>
                        - Bookings
                    <?php endif; ?>
                </h5>
                <?php if (!empty($related_data)): ?>
                    <span class="badge bg-orange"><?php echo count($related_data); ?> items</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($related_data)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted fs-1"></i>
                        <p class="text-muted mt-2">No recent activity</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php if ($user['role'] === 'landlord'): ?>
                            <!-- Landlord Hostels -->
                            <?php foreach ($related_data as $hostel): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <?php if ($hostel['primary_image']): ?>
                                        <img src="../uploads/hostels/<?php echo $hostel['primary_image']; ?>" 
                                             class="rounded me-3" width="50" height="50" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded me-3 d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-image text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($hostel['name']); ?></h6>
                                        <p class="mb-1 text-muted small">
                                            <?php echo htmlspecialchars($hostel['city'] . ', ' . $hostel['state']); ?> • 
                                            ₦<?php echo number_format($hostel['price_per_month']); ?>/month
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo $hostel['available_rooms']; ?> rooms available • 
                                                <?php echo $hostel['booking_count']; ?> bookings
                                            </small>
                                            <span class="badge bg-<?php echo $hostel['is_approved'] ? 'success' : 'warning'; ?>">
                                                <?php echo $hostel['is_approved'] ? 'Approved' : 'Pending'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                        <?php elseif ($user['role'] === 'tenant'): ?>
                            <!-- Tenant Bookings -->
                            <?php foreach ($related_data as $booking): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($booking['hostel_name']); ?></h6>
                                        <p class="mb-1 text-muted small">
                                            <?php echo htmlspecialchars($booking['city'] . ', ' . $booking['state']); ?> • 
                                            <?php echo $booking['duration_months']; ?> months
                                        </p>
                                        <small class="text-muted">
                                            <?php echo format_date($booking['check_in_date']); ?> - <?php echo format_date($booking['check_out_date']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <strong class="d-block">₦<?php echo number_format($booking['total_amount']); ?></strong>
                                        <span class="badge bg-<?php 
                                            switch($booking['status']) {
                                                case 'confirmed': echo 'success'; break;
                                                case 'pending': echo 'warning'; break;
                                                case 'cancelled': echo 'danger'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>"><?php echo ucfirst($booking['status']); ?></span>
                                        <?php if ($booking['payment_status']): ?>
                                            <br><small class="text-<?php echo $booking['payment_status'] === 'success' ? 'success' : 'warning'; ?>">
                                                Payment: <?php echo ucfirst($booking['payment_status']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- View All Link -->
                    <div class="text-center mt-3">
                        <?php if ($user['role'] === 'landlord'): ?>
                            <a href="hostels.php?landlord=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-orange">
                                View All Hostels
                            </a>
                        <?php elseif ($user['role'] === 'tenant'): ?>
                            <a href="bookings.php?tenant=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-orange">
                                View All Bookings
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tenant Reviews -->
        <?php if ($user['role'] === 'tenant' && isset($reviews) && !empty($reviews)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Reviews</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($reviews as $review): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($review['hostel_name']); ?></h6>
                                <div class="text-warning mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($review['comment']); ?></p>
                            </div>
                            <small class="text-muted"><?php echo format_date($review['created_at']); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>