<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

Auth::requireRole(['admin']);
$page_title = "Edit User";
require_once '../includes/headers/header_admin.php';

$db = new Database();
$error = '';
$success = '';

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

// Handle form submission
if ($_POST) {
    try {
        // Validate required fields
        $required = ['first_name', 'last_name', 'email', 'role'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email exists for other users
        $stmt = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$_POST['email'], $user_id]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Email already registered by another user");
        }

        // Validate role
        if (!in_array($_POST['role'], ['admin', 'landlord', 'tenant'])) {
            throw new Exception("Invalid role selected");
        }

        // Prepare update data
        $update_data = [
            'first_name' => sanitize_input($_POST['first_name']),
            'last_name' => sanitize_input($_POST['last_name']),
            'email' => sanitize_input($_POST['email']),
            'phone' => sanitize_input($_POST['phone'] ?? ''),
            'role' => $_POST['role'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Update password if provided
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                throw new Exception("Password must be at least 6 characters long");
            }
            $update_data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        // Build update query
        $set_parts = [];
        $params = [];
        foreach ($update_data as $field => $value) {
            $set_parts[] = "$field = ?";
            $params[] = $value;
        }
        $params[] = $user_id;

        $sql = "UPDATE users SET " . implode(', ', $set_parts) . " WHERE id = ?";
        $db->query($sql, $params);

        $success = "User updated successfully";
        
        // Refresh user data
        $stmt = $db->query("SELECT * FROM users WHERE id = ?", [$user_id]);
        $user = $stmt->fetch();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <h1 class="h2">Edit User</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="users.php" class="btn btn-outline-orange">
            <i class="bi bi-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">User Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Role *</label>
                                <select name="role" class="form-select" required>
                                    <option value="tenant" <?php echo $user['role'] === 'tenant' ? 'selected' : ''; ?>>Tenant</option>
                                    <option value="landlord" <?php echo $user['role'] === 'landlord' ? 'selected' : ''; ?>>Landlord</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" 
                                           id="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Active User
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Leave blank to keep current password" minlength="6">
                        <div class="form-text">
                            Minimum 6 characters. Leave empty to keep current password.
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="users.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-orange">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- User Stats -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">User Statistics</h5>
            </div>
            <div class="card-body">
                <?php
                $stats = [];
                if ($user['role'] === 'landlord') {
                    $stmt = $db->query("
                        SELECT 
                            COUNT(*) as total_hostels,
                            SUM(is_approved = TRUE) as approved_hostels,
                            SUM(is_approved = FALSE) as pending_hostels
                        FROM hostels 
                        WHERE landlord_id = ?
                    ", [$user_id]);
                    $stats = $stmt->fetch();
                } elseif ($user['role'] === 'tenant') {
                    $stmt = $db->query("
                        SELECT 
                            COUNT(*) as total_bookings,
                            SUM(status = 'confirmed') as confirmed_bookings,
                            COUNT(*) as total_reviews
                        FROM bookings 
                        LEFT JOIN reviews ON bookings.id = reviews.booking_id
                        WHERE tenant_id = ?
                    ", [$user_id]);
                    $stats = $stmt->fetch();
                }
                ?>
                
                <div class="text-center">
                    <div class="mb-3">
                        <img src="../uploads/avatars/<?php echo $user['avatar']; ?>" 
                             class="rounded-circle" width="80" height="80" 
                             style="object-fit: cover;" 
                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>&background=f97316&color=fff'">
                    </div>
                    
                    <h5><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <span class="badge bg-<?php 
                        switch($user['role']) {
                            case 'admin': echo 'danger'; break;
                            case 'landlord': echo 'warning'; break;
                            case 'tenant': echo 'info'; break;
                            default: echo 'secondary';
                        }
                    ?>"><?php echo get_role_display($user['role']); ?></span>
                    
                    <div class="mt-3">
                        <small class="text-muted">Member since <?php echo format_date($user['created_at']); ?></small>
                    </div>
                </div>

                <?php if ($user['role'] === 'landlord' && !empty($stats)): ?>
                    <hr>
                    <div class="row text-center">
                        <div class="col-4">
                            <h6 class="mb-0"><?php echo $stats['total_hostels']; ?></h6>
                            <small class="text-muted">Hostels</small>
                        </div>
                        <div class="col-4">
                            <h6 class="mb-0 text-success"><?php echo $stats['approved_hostels']; ?></h6>
                            <small class="text-muted">Approved</small>
                        </div>
                        <div class="col-4">
                            <h6 class="mb-0 text-warning"><?php echo $stats['pending_hostels']; ?></h6>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                <?php elseif ($user['role'] === 'tenant' && !empty($stats)): ?>
                    <hr>
                    <div class="row text-center">
                        <div class="col-4">
                            <h6 class="mb-0"><?php echo $stats['total_bookings']; ?></h6>
                            <small class="text-muted">Bookings</small>
                        </div>
                        <div class="col-4">
                            <h6 class="mb-0 text-success"><?php echo $stats['confirmed_bookings']; ?></h6>
                            <small class="text-muted">Confirmed</small>
                        </div>
                        <div class="col-4">
                            <h6 class="mb-0 text-info"><?php echo $stats['total_reviews']; ?></h6>
                            <small class="text-muted">Reviews</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">Danger Zone</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Once you delete a user, there is no going back. Please be certain.
                </p>
                
                <?php if ($user['role'] !== 'admin'): ?>
                    <button type="button" class="btn btn-outline-danger w-100" 
                            data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Delete User
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-danger w-100" disabled>
                        <i class="bi bi-shield-lock"></i> Admin Protected
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>?</p>
                <p class="text-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    This action cannot be undone. All associated data will be permanently removed.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-danger">Delete User</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>