<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('landlord');
$page_title = "Landlord Dashboard";
$landlord_id = $_SESSION['user_id'];

// --- Fetch Stats ---
// Total Hostels
$stmt_hostels = $pdo->prepare("SELECT COUNT(id) FROM hostels WHERE landlord_id = ?");
$stmt_hostels->execute([$landlord_id]);
$total_hostels = $stmt_hostels->fetchColumn();

// Pending Bookings
$stmt_bookings = $pdo->prepare("
    SELECT COUNT(id) FROM bookings 
    WHERE landlord_id = ? AND (status = 'paid')
"); // 'paid' means tenant paid, landlord needs to 'confirm'
$stmt_bookings->execute([$landlord_id]);
$pending_bookings = $stmt_bookings->fetchColumn();

// Total Earnings (sum of all 'paid' or 'completed' bookings)
$stmt_earnings = $pdo->prepare("
    SELECT SUM(total_price) FROM bookings 
    WHERE landlord_id = ? AND (status = 'paid' OR status = 'confirmed' OR status = 'completed')
");
$stmt_earnings->execute([$landlord_id]);
$total_earnings = $stmt_earnings->fetchColumn() ?? 0;

// Fetch recent bookings (e.g., last 5)
$stmt_recent = $pdo->prepare("
    SELECT b.*, h.name as hostel_name, u.name as tenant_name
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN users u ON b.tenant_id = u.id
    WHERE b.landlord_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt_recent->execute([$landlord_id]);
$recent_bookings = $stmt_recent->fetchAll();

// --- Header ---
get_header($pdo); 
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Welcome, <?php echo sanitize($_SESSION['user_name']); ?>!</h1>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card stat-card border-primary p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-title text-primary">Total Hostels</div>
                        <div class="card-text"><?php echo $total_hostels; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-building opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card border-warning p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-title text-warning">Pending Bookings</div>
                        <div class="card-text"><?php echo $pending_bookings; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-clock-history opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card border-success p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-title text-success">Total Revenue</div>
                        <div class="card-text"><?php echo format_price($total_earnings); ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-cash-stack opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Recent Booking Requests</h5>
            <a href="<?php echo BASE_URL; ?>/landlord/bookings.php" class="btn btn-sm btn-outline-orange">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Hostel</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_bookings)): ?>
                            <tr>
                                <td colspan="5" class="text-center p-4">You have no recent bookings.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td><?php echo sanitize($booking['tenant_name']); ?></td>
                                    <td><?php echo sanitize($booking['hostel_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['start_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $booking['status'] == 'paid' ? 'success' : ($booking['status'] == 'pending' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst(sanitize($booking['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/landlord/booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-orange">
                                            Manage
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// --- Footer ---
get_footer(); 
?>