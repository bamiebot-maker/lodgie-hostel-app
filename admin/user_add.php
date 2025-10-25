<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

Auth::requireRole(['admin']);
$page_title = "Add New User";
require_once '../includes/headers/header_admin.php';

$db = new Database();
$error = '';
$success = '';

if ($_POST) {
    try {
        // Validate required fields
        $required = ['first_name', 'last_name', 'email', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email exists
        $stmt = $db->query("SELECT id FROM users WHERE email = ?", [$_POST['email']]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Email already registered");
        }

        // Validate role
        if (!in_array($_POST['role'], ['admin', 'landlord', 'tenant'])) {
            throw new Exception("Invalid role selected");
        }

        // Validate password
        if (strlen($_POST['password']) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }

        // Hash password
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insert user
        $sql = "INSERT INTO users (first_name, last_name, email, password, phone, role, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($sql, [
            sanitize_input($_POST['first_name']),
            sanitize_input($_POST['last_name']),
            sanitize_input($_POST['email']),
            $hashedPassword,
            sanitize_input($_POST['phone'] ?? ''),
            $_POST['role'],
            isset($_POST['is_active']) ? 1 : 0
        ]);

        $user_id = $db->lastInsertId();
        $success = "User created successfully! User ID: " . $user_id;
        
        // Reset form
        $_POST = [];

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <h1 class="h2">Add New User</h1>
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

<div class="row justify-content-center">
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
                                       value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" 
                                       value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo $_POST['email'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo $_POST['phone'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Role *</label>
                                <select name="role" class="form-select" required>
                                    <option value="">Select Role</option>
                                    <option value="tenant" <?php echo ($_POST['role'] ?? '') === 'tenant' ? 'selected' : ''; ?>>Tenant</option>
                                    <option value="landlord" <?php echo ($_POST['role'] ?? '') === 'landlord' ? 'selected' : ''; ?>>Landlord</option>
                                    <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" 
                                           id="is_active" <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Active User
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" 
                               value="<?php echo $_POST['password'] ?? ''; ?>" required minlength="6">
                        <div class="form-text">
                            Minimum 6 characters
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="users.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-orange">Create User</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Role Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Role Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-person fs-1 text-info"></i>
                            <h6 class="mt-2">Tenant</h6>
                            <small class="text-muted">
                                Can browse and book hostels, make payments, and leave reviews.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-house fs-1 text-warning"></i>
                            <h6 class="mt-2">Landlord</h6>
                            <small class="text-muted">
                                Can add and manage hostels, view bookings, and receive payments.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-shield-check fs-1 text-danger"></i>
                            <h6 class="mt-2">Admin</h6>
                            <small class="text-muted">
                                Full system access to manage users, hostels, bookings, and payments.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    
    if (password.value !== confirmPassword.value) {
        e.preventDefault();
        alert('Passwords do not match!');
        confirmPassword.focus();
    }
});
</script>

<?php include '../includes/footer.php'; ?>