<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('admin');
$page_title = "Admin Dashboard";

// --- Fetch Stats ---
// Total Users
$total_users = $pdo->query("SELECT COUNT(id) FROM users WHERE role != 'admin'")->fetchColumn();
// Total Landlords
$total_landlords = $pdo->query("SELECT COUNT(id) FROM users WHERE role = 'landlord'")->fetchColumn();
// Total Tenants
$total_tenants = $pdo->query("SELECT COUNT(id) FROM users WHERE role = 'tenant'")->fetchColumn();
// Total Hostels
$total_hostels = $pdo->query("SELECT COUNT(id) FROM hostels")->fetchColumn();
// Pending Hostels
$pending_hostels = $pdo->query("SELECT COUNT(id) FROM hostels WHERE status = 'pending'")->fetchColumn();
// Total Bookings
$total_bookings = $pdo->query("SELECT COUNT(id) FROM bookings")->fetchColumn();
// Total Revenue
$total_revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'success'")->fetchColumn() ?? 0;

// Fetch recent users
$stmt_users = $pdo->query("SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt_users->fetchAll();

// Fetch pending hostels
$stmt_hostels = $pdo->query("SELECT h.id, h.name, u.name as landlord_name 
                             FROM hostels h 
                             JOIN users u ON h.landlord_id = u.id
                             WHERE h.status = 'pending' 
                             ORDER BY h.created_at DESC LIMIT 5");
$recent_pending_hostels = $stmt_hostels->fetchAll();

// --- Header ---
get_header($pdo); 
?>

<h1 class="h3 mb-4">Admin Dashboard</h1>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-primary p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="card-title text-primary">Total Users</div>
                    <div class="card-text"><?php echo $total_users; ?></div>
                    <small class="text-muted"><?php echo $total_landlords; ?> Landlords, <?php echo $total_tenants; ?> Tenants</small>
                </div>
                <div class="icon"><i class="bi bi-people-fill opacity-25"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-info p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="card-title text-info">Total Hostels</div>
                    <div class="card-text"><?php echo $total_hostels; ?></div>
                    <small class="text-muted"><?php echo $pending_hostels; ?> Pending</small>
                </div>
                <div class="icon"><i class="bi bi-building opacity-25"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-warning p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="card-title text-warning">Total Bookings</div>
                    <div class="card-text"><?php echo $total_bookings; ?></div>
                    <small class="text-muted">&nbsp;</small> </div>
                <div class="icon"><i class="bi bi-calendar-check opacity-25"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-success p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="card-title text-success">Total Revenue</div>
                    <div class="card-text"><?php echo format_price($total_revenue); ?></div>
                    <small class="text-muted">&nbsp;</small> </div>
                <div class="icon"><i class="bi bi-cash-stack opacity-25"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pending Hostel Approvals</h5>
                <a href="<?php echo BASE_URL; ?>/admin/hostels.php" class="btn btn-sm btn-outline-orange">Manage All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Hostel Name</th><th>Landlord</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_pending_hostels)): ?>
                                <tr><td colspan="3" class="text-center p-3">No pending hostels.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_pending_hostels as $hostel): ?>
                                    <tr>
                                        <td><?php echo sanitize($hostel['name']); ?></td>
                                        <td><?php echo sanitize($hostel['landlord_name']); ?></td>
                                        <td><a href="<?php echo BASE_URL; ?>/admin/hostel_details.php?id=<?php echo $hostel['id']; ?>" class="btn btn-sm btn-warning">Review</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recently Registered Users</h5>
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-outline-orange">Manage All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Role</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?php echo sanitize($user['name']); ?></td>
                                    <td><?php echo sanitize($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] == 'landlord' ? 'info' : 'secondary'; ?>">
                                            <?php echo ucfirst(sanitize($user['role'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Site Analytics (Placeholder)</h5>
            </div>
            <div class="card-body">
                <canvas id="myChart"></canvas>
                <p class="text-center text-muted mt-3">Include Chart.js library to enable analytics charts.</p>
            </div>
        </div>
    </div>
</div>

<?php
// --- Footer ---
// Admin footer is different; it just closes the wrapper
?>
        </main> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
</body>
</html>