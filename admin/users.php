<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('admin');
$page_title = "Manage Users";

// --- Handle User Actions (Suspend/Activate/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
    } else {
        $user_id = (int) $_POST['user_id'];
        $action = $_POST['user_action'];
        
        // Prevent admin from acting on themselves
        if ($user_id === $_SESSION['user_id']) {
            $_SESSION['error_flash'] = 'You cannot modify your own account.';
            redirect('admin/users.php');
        }

        try {
            if ($action === 'delete') {
                // Hard delete. Be careful! Cascading deletes might be an issue.
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$user_id]);
                $_SESSION['success_flash'] = 'User permanently deleted.';
            } elseif ($action === 'suspend') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role != 'admin'");
                $stmt->execute([$user_id]);
                $_SESSION['success_flash'] = 'User account suspended.';
            } elseif ($action === 'activate') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role != 'admin'");
                $stmt->execute([$user_id]);
                $_SESSION['success_flash'] = 'User account activated.';
            }
        } catch (PDOException $e) {
            $_SESSION['error_flash'] = 'Action failed. The user might be tied to critical data (like hostels or bookings).';
            error_log('Admin User Action Error: ' . $e->getMessage());
        }
    }
    redirect('admin/users.php');
}


// --- Fetch All Users (excluding admins) ---
$filter_role = sanitize($_GET['role'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$sql = "SELECT * FROM users WHERE role != 'admin'";
$params = [];

if (!empty($filter_role)) {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$csrf_token = generate_csrf_token();

// --- Header ---
get_header($pdo); // Pass $pdo for notifications
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Manage Users</h1>
    </div>

<?php display_flash_messages(); ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form action="users.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search by name or email...">
            </div>
            <div class="col-md-4">
                <label for="role" class="form-label">Filter by Role</label>
                <select id="role" name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="landlord" <?php echo $filter_role === 'landlord' ? 'selected' : ''; ?>>Landlords</option>
                    <option value="tenant" <?php echo $filter_role === 'tenant' ? 'selected' : ''; ?>>Tenants</option>
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
        <h5 class="mb-0">All Users (<?php echo count($users); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="text-center p-4">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" class="rounded-circle me-2" width="30" height="30" alt="Avatar">
                                    <strong><?php echo sanitize($user['name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo sanitize($user['email']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo sanitize($user['phone']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] == 'landlord' ? 'info' : 'secondary'; ?>">
                                        <?php echo ucfirst(sanitize($user['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst(sanitize($user['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton-<?php echo $user['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton-<?php echo $user['id']; ?>">
                                            <li>
                                                <form action="users.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <?php if ($user['status'] == 'active'): ?>
                                                        <button type="submit" name="user_action" value="suspend" class="dropdown-item text-warning">
                                                            <i class="bi bi-pause-circle me-1"></i> Suspend
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="user_action" value="activate" class="dropdown-item text-success">
                                                            <i class="bi bi-play-circle me-1"></i> Activate
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="users.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to PERMANENTLY DELETE this user? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="user_action" value="delete" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash-fill me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
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
// Admin footer is built into admin/dashboard.php (and other admin pages)
?>
        </main> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
</body>
</html>