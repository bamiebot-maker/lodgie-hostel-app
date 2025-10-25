<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php'; // For notifications

// --- Page-Specific Logic ---
protect_page('admin');
$page_title = "Manage Hostels";

// --- Handle Hostel Status Update (Approve/Reject) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hostel_action'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
    } else {
        $hostel_id = (int) $_POST['hostel_id'];
        $landlord_id = (int) $_POST['landlord_id']; // Get from form
        $hostel_name = sanitize($_POST['hostel_name']); // Get from form
        $action = $_POST['hostel_action'];
        
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE hostels SET status = 'approved' WHERE id = ?");
                $stmt->execute([$hostel_id]);
                $_SESSION['success_flash'] = 'Hostel approved and is now live.';
                // Notify Landlord
                create_notification($pdo, $landlord_id, 'Hostel Approved', "Congratulations! Your hostel '$hostel_name' has been approved.", 'success', 'landlord/my_hostels.php');
            
            } elseif ($action === 'reject') {
                // We should also delete it to keep the DB clean, or add a 'rejection_reason' column
                // For simplicity, we just set status to 'rejected'.
                $stmt = $pdo->prepare("UPDATE hostels SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$hostel_id]);
                $_SESSION['success_flash'] = 'Hostel rejected.';
                // Notify Landlord
                create_notification($pdo, $landlord_id, 'Hostel Rejected', "Your hostel '$hostel_name' was rejected. Please review and resubmit.", 'danger', 'landlord/my_hostels.php');
            
            } elseif ($action === 'unpublish') {
                $stmt = $pdo->prepare("UPDATE hostels SET status = 'pending' WHERE id = ?");
                $stmt->execute([$hostel_id]);
                $_SESSION['success_flash'] = 'Hostel unpublished and set to pending.';
                // Notify Landlord
                create_notification($pdo, $landlord_id, 'Hostel Unpublished', "Your hostel '$hostel_name' was unpublished by an admin. It is now pending review.", 'warning', 'landlord/my_hostels.php');
            }
        } catch (PDOException $e) {
            $_SESSION['error_flash'] = 'Action failed. ' . $e->getMessage();
        }
    }
    redirect('admin/hostels.php');
}


// --- Fetch All Hostels ---
$filter_status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$sql = "SELECT h.*, u.name as landlord_name 
        FROM hostels h 
        JOIN users u ON h.landlord_id = u.id";
$params = [];
$conditions = [];

if (!empty($filter_status)) {
    $conditions[] = "h.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $conditions[] = "(h.name LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY 
        CASE h.status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
            ELSE 4
        END, 
        h.created_at DESC";
        
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hostels = $stmt->fetchAll();

$csrf_token = generate_csrf_token();

// --- Header ---
get_header($pdo); // Pass $pdo for notifications
?>

<h1 class="h3 mb-4">Manage Hostels</h1>

<?php display_flash_messages(); ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form action="hostels.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search by hostel or landlord...">
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">Filter by Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-orange w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">All Hostels (<?php echo count($hostels); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hostel</th>
                        <th>Landlord</th>
                        <th>Location</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hostels)): ?>
                        <tr><td colspan="6" class="text-center p-4">No hostels found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($hostels as $hostel): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($hostel['name']); ?></strong>
                                    <br>
                                    <a href="<?php echo BASE_URL; ?>/hostel_details.php?id=<?php echo $hostel['id']; ?>" class="small" target="_blank">View Listing</a>
                                </td>
                                <td><?php echo sanitize($hostel['landlord_name']); ?></td>
                                <td><?php echo sanitize($hostel['city']); ?></td>
                                <td class="fw-bold text-orange">
                                    <?php echo format_price($hostel['price_per_month']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        $status_map = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                                        echo $status_map[$hostel['status']] ?? 'secondary'; 
                                    ?>">
                                        <?php echo ucfirst(sanitize($hostel['status'])); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($hostel['status'] === 'pending'): ?>
                                        <form action="hostels.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                                            <input type="hidden" name="landlord_id" value="<?php echo $hostel['landlord_id']; ?>">
                                            <input type="hidden" name="hostel_name" value="<?php echo sanitize($hostel['name']); ?>">
                                            <button type="submit" name="hostel_action" value="approve" class="btn btn-sm btn-success" title="Approve">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form action="hostels.php" method="POST" class="d-inline ms-1">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                                            <input type="hidden" name="landlord_id" value="<?php echo $hostel['landlord_id']; ?>">
                                            <input type="hidden" name="hostel_name" value="<?php echo sanitize($hostel['name']); ?>">
                                            <button type="submit" name="hostel_action" value="reject" class="btn btn-sm btn-danger" title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($hostel['status'] === 'approved'): ?>
                                        <form action="hostels.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                                            <input type="hidden" name="landlord_id" value="<?php echo $hostel['landlord_id']; ?>">
                                            <input type="hidden" name="hostel_name" value="<?php echo sanitize($hostel['name']); ?>">
                                            <button type="submit" name="hostel_action" value="unpublish" class="btn btn-sm btn-warning" title="Unpublish (set to pending)">
                                                <i class="bi bi-eye-slash-fill"></i> Unpublish
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">No action</span>
                                    <?php endif; ?>
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
?>
        </main> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>