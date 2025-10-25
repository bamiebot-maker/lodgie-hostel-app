<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php'; // $pdo is created here
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('tenant');
$page_title = "My Dashboard";
$tenant_id = $_SESSION['user_id'];

// Fetch recent bookings (e.g., last 3)
$stmt_bookings = $pdo->prepare("
    SELECT b.*, h.name as hostel_name
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    WHERE b.tenant_id = ?
    ORDER BY b.created_at DESC
    LIMIT 3
");
$stmt_bookings->execute([$tenant_id]);
$recent_bookings = $stmt_bookings->fetchAll();

// **MISSING CODE:** Fetch stats
$stmt_total = $pdo->prepare("SELECT COUNT(id) FROM bookings WHERE tenant_id = ?");
$stmt_total->execute([$tenant_id]);
$total_bookings = $stmt_total->fetchColumn();

$stmt_paid = $pdo->prepare("SELECT COUNT(id) FROM bookings WHERE tenant_id = ? AND status IN ('paid', 'confirmed')");
$stmt_paid->execute([$tenant_id]);
$active_bookings = $stmt_paid->fetchColumn();

// --- Header ---
get_header($pdo); 
?>

<h1 class="h3 mb-4">Welcome back, <?php echo sanitize($_SESSION['user_name']); ?>!</h1>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="card stat-card border-primary p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-title text-primary">Total Bookings</div>
                        <div class="card-text"><?php echo $total_bookings; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-calendar-check opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card stat-card border-success p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-title text-success">Active Bookings</div>
                        <div class="card-text"><?php echo $active_bookings; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-building-check opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Quick Actions</h5>
        </div>
        <div class="card-body">
            <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-orange">
                <i class="bi bi-search me-1"></i> Find a New Hostel
            </a>
            <a href="<?php echo BASE_URL; ?>/tenant/bookings.php" class="btn btn-outline-secondary">
                <i class="bi bi-list-check me-1"></i> View All My Bookings
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">My Recent Bookings</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Hostel</th>
                            <th>Date</th>
                            <th>Price</th>
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
                                    <td><?php echo sanitize($booking['hostel_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['start_date'])); ?></td>
                                    <td><?php echo format_price($booking['total_price']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            $status_map = ['pending' => 'warning', 'paid' => 'success', 'confirmed' => 'primary', 'cancelled' => 'danger', 'completed' => 'secondary'];
                                            echo $status_map[$booking['status']] ?? 'light'; 
                                        ?>">
                                            <?php echo ucfirst(sanitize($booking['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/tenant/bookings.php?highlight=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-orange">
                                            View
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

<?php
// --- Footer ---
get_footer(); 
?>