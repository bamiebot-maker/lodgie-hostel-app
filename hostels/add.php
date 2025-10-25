<?php
$page_title = "My Hostels - Lodgie";
require_once '../includes/header-auth.php';
require_role('landlord');

// Get landlord's hostels
$hostels = $db->fetchAll(
    "SELECT h.*, 
     (SELECT COUNT(*) FROM bookings WHERE hostel_id = h.id AND status IN ('paid', 'confirmed')) as booking_count,
     (SELECT filename FROM hostel_images WHERE hostel_id = h.id AND is_cover = 1 LIMIT 1) as cover_image
     FROM hostels h 
     WHERE h.landlord_id = ? 
     ORDER BY h.created_at DESC",
    [$_SESSION['user_id']]
);

// Get stats
$stats = $db->fetch(
    "SELECT 
        COUNT(*) as total_hostels,
        SUM(CASE WHEN is_published = 1 AND is_verified = 1 THEN 1 ELSE 0 END) as published_hostels,
        SUM(available_rooms) as total_available_rooms
     FROM hostels 
     WHERE landlord_id = ?",
    [$_SESSION['user_id']]
);
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item active">My Hostels</li>
            </ol>
        </nav>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">My Hostels</h1>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add Hostel
            </a>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="number"><?php echo $stats['total_hostels']; ?></div>
            <p class="text-muted mb-0">Total Hostels</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="number"><?php echo $stats['published_hostels']; ?></div>
            <p class="text-muted mb-0">Published</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="number"><?php echo $stats['total_available_rooms']; ?></div>
            <p class="text-muted mb-0">Available Rooms</p>
        </div>
    </div>
</div>

<!-- Hostels List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Hostel Listings</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($hostels)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Hostel</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th>Rooms</th>
                            <th>Status</th>
                            <th>Bookings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hostels as $hostel): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if ($hostel['cover_image']): ?>
                                        <img src="../uploads/hostels/<?php echo $hostel['cover_image']; ?>" 
                                             alt="<?php echo sanitize_input($hostel['title']); ?>" 
                                             class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center me-3" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-home text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo sanitize_input($hostel['title']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo ucfirst($hostel['room_type']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo sanitize_input($hostel['city']); ?>,<br>
                                <small class="text-muted"><?php echo sanitize_input($hostel['state']); ?></small>
                            </td>
                            <td>
                                <strong>₦<?php echo number_format($hostel['price_per_month'], 2); ?></strong>
                                <br>
                                <small class="text-muted">per month</small>
                            </td>
                            <td>
                                <?php echo $hostel['available_rooms']; ?> / <?php echo $hostel['total_rooms']; ?>
                                <br>
                                <small class="text-muted">available</small>
                            </td>
                            <td>
                                <?php if ($hostel['is_verified'] && $hostel['is_published']): ?>
                                    <span class="badge bg-success">Published</span>
                                <?php elseif ($hostel['is_published'] && !$hostel['is_verified']): ?>
                                    <span class="badge bg-warning">Pending Review</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo $hostel['booking_count']; ?></strong>
                                <br>
                                <small class="text-muted">bookings</small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?php echo $hostel['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $hostel['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $hostel['id']; ?>" class="btn btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this hostel?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-home fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No hostels yet</h4>
                <p class="text-muted mb-4">Start by adding your first hostel listing</p>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add Your First Hostel
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>