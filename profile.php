<?php
$page_title = "My Profile - Lodgie";
require_once 'includes/header-auth.php';

$user = $db->fetch(
    "SELECT * FROM users WHERE id = ?",
    [$_SESSION['user_id']]
);

if (!$user) {
    $_SESSION['message'] = 'User not found';
    $_SESSION['message_type'] = 'danger';
    redirect('index.php');
}

// Get user stats based on role
if (has_role('student')) {
    $stats = $db->fetch(
        "SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings
         FROM bookings 
         WHERE student_id = ?",
        [$_SESSION['user_id']]
    );
} elseif (has_role('landlord')) {
    $stats = $db->fetch(
        "SELECT 
            COUNT(*) as total_hostels,
            SUM(CASE WHEN is_published = 1 AND is_verified = 1 THEN 1 ELSE 0 END) as published_hostels,
            (SELECT COUNT(*) FROM bookings b JOIN hostels h ON b.hostel_id = h.id WHERE h.landlord_id = ?) as total_bookings
         FROM hostels 
         WHERE landlord_id = ?",
        [$_SESSION['user_id'], $_SESSION['user_id']]
    );
}
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">My Profile</li>
            </ol>
        </nav>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">My Profile</h1>
        </div>
    </div>
</div>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong>Full Name</strong></label>
                        <p><?php echo sanitize_input($user['full_name']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong>Email Address</strong></label>
                        <p><?php echo sanitize_input($user['email']); ?></p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong>Phone Number</strong></label>
                        <p><?php echo sanitize_input($user['phone']) ?: 'Not provided'; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong>Account Type</strong></label>
                        <p>
                            <span class="badge bg-<?php 
                                echo match($user['role']) {
                                    'student' => 'primary',
                                    'landlord' => 'success',
                                    'admin' => 'danger',
                                    default => 'secondary'
                                };
                            ?>"><?php echo ucfirst($user['role']); ?></span>
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong>Account Status</strong></label>
                        <p>
                            <?php if ($user['is_verified']): ?>
                                <span class="badge bg-success">Verified</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Pending Verification</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong>Member Since</strong></label>
                        <p><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Stats</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (has_role('student')): ?>
                        <div class="col-md-6 text-center">
                            <div class="number text-primary"><?php echo $stats['total_bookings']; ?></div>
                            <p class="text-muted mb-0">Total Bookings</p>
                        </div>
                        <div class="col-md-6 text-center">
                            <div class="number text-success"><?php echo $stats['confirmed_bookings']; ?></div>
                            <p class="text-muted mb-0">Confirmed</p>
                        </div>
                    <?php elseif (has_role('landlord')): ?>
                        <div class="col-md-4 text-center">
                            <div class="number text-primary"><?php echo $stats['total_hostels']; ?></div>
                            <p class="text-muted mb-0">Total Hostels</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="number text-success"><?php echo $stats['published_hostels']; ?></div>
                            <p class="text-muted mb-0">Published</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="number text-info"><?php echo $stats['total_bookings']; ?></div>
                            <p class="text-muted mb-0">Total Bookings</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions & Quick Links -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (has_role('student')): ?>
                        <a href="search.php" class="btn btn-outline-primary">
                            <i class="fas fa-search me-2"></i>Browse Hostels
                        </a>
                        <a href="bookings.php" class="btn btn-outline-success">
                            <i class="fas fa-calendar me-2"></i>My Bookings
                        </a>
                    <?php elseif (has_role('landlord')): ?>
                        <a href="hostels/add.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-2"></i>Add New Hostel
                        </a>
                        <a href="hostels/my.php" class="btn btn-outline-success">
                            <i class="fas fa-home me-2"></i>My Hostels
                        </a>
                        <a href="bookings.php" class="btn btn-outline-info">
                            <i class="fas fa-calendar me-2"></i>Booking Requests
                        </a>
                    <?php elseif (has_role('admin')): ?>
                        <a href="admin/dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                        </a>
                        <a href="admin/hostels.php" class="btn btn-outline-success">
                            <i class="fas fa-home me-2"></i>Manage Hostels
                        </a>
                        <a href="admin/users.php" class="btn btn-outline-info">
                            <i class="fas fa-users me-2"></i>Manage Users
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Account Status -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Account Status</h5>
            </div>
            <div class="card-body">
                <?php if ($user['is_verified']): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Account Verified</strong>
                        <p class="mb-0 small">Your account is fully verified and active.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Pending Verification</strong>
                        <p class="mb-0 small">
                            <?php if (has_role('landlord')): ?>
                                Your landlord account is pending admin verification. You can still add hostels, but they will need verification before being published.
                            <?php else: ?>
                                Your account verification is in progress.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="d-grid">
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>