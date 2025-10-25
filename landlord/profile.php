<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php'; // $pdo is created here
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('landlord'); // Protects for landlord role
$page_title = "My Profile";
$user_id = $_SESSION['user_id'];

// --- Fetch Current User Data ---
$stmt = $pdo->prepare("SELECT name, email, phone, avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error_flash'] = 'User not found.';
    redirect('logout.php');
}

// --- Handle Profile Update (Info) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
    } else {
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);

        if (empty($name) || empty($phone)) {
            $_SESSION['error_flash'] = 'Name and Phone cannot be empty.';
        } else {
            try {
                $stmt_update = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $stmt_update->execute([$name, $phone, $user_id]);

                // Update session
                $_SESSION['user_name'] = $name;

                $_SESSION['success_flash'] = 'Profile updated successfully.';
            } catch (PDOException $e) {
                $_SESSION['error_flash'] = 'Failed to update profile.';
            }
        }
    }
    // Redirect back to landlord profile
    redirect('landlord/profile.php');
}

// --- Handle Password Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Get current hashed password
        $stmt_pass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_pass->execute([$user_id]);
        $hashed_password = $stmt_pass->fetchColumn();

        if (password_verify($current_password, $hashed_password)) {
            if (strlen($new_password) < 8) {
                $_SESSION['error_flash'] = 'New password must be at least 8 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $_SESSION['error_flash'] = 'New passwords do not match.';
            } else {
                // Hash new password and update
                $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt_new_pass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_new_pass->execute([$new_hashed_password, $user_id]);
                $_SESSION['success_flash'] = 'Password changed successfully.';
            }
        } else {
            $_SESSION['error_flash'] = 'Incorrect current password.';
        }
    }
    // Redirect back to landlord profile
    redirect('landlord/profile.php');
}


$csrf_token = generate_csrf_token();

// --- Header ---
// **IMPORTANT:** Pass $pdo to the header
get_header($pdo);
?>

<h1 class="h3 mb-4">My Profile</h1>

    <?php display_flash_messages(); ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body p-4">
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3 row">
                            <label for="email" class="col-sm-3 col-form-label">Email</label>
                            <div class="col-sm-9">
                                <input type="email" readonly class="form-control-plaintext" id="email" value="<?php echo sanitize($user['email']); ?>">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="name" class="col-sm-3 col-form-label">Full Name</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize($user['name']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="phone" class="col-sm-3 col-form-label">Phone</label>
                            <div class="col-sm-9">
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo sanitize($user['phone']); ?>" required>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" name="update_info" class="btn btn-orange">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div> <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body p-4">
                    <form action="profile.php" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                            <div class="invalid-feedback">Must be at least 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="update_password" class="btn btn-outline-orange">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div> </div> <?php
// --- Footer ---
get_footer();
?>