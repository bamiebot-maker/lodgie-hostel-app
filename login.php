<?php
// --- Core Includes ---
// **FIX 1:** This must be at the top to start the session
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// --- Page-Specific Logic ---
$page_title = "Login";

// If user is already logged in, redirect them
if (is_logged_in()) {
    $role = $_SESSION['role'];
    if ($role === 'admin') redirect('admin/dashboard.php');
    if ($role === 'landlord') redirect('landlord/dashboard.php');
    if ($role === 'tenant') redirect('tenant/dashboard.php');
}

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request. Please try again.';
        redirect('login.php');
    }

    // Rate Limiting
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_login_attempt'] = time();
    }
    if ($_SESSION['login_attempts'] >= 5) {
        $lockout_time = 15 * 60; // 15 mins
        if (time() - $_SESSION['last_login_attempt'] < $lockout_time) {
            $remaining = ceil(($lockout_time - (time() - $_SESSION['last_login_attempt'])) / 60);
            $_SESSION['error_flash'] = "Too many failed attempts. Please try again in $remaining minutes.";
            redirect('login.php');
        } else {
            // Reset after lockout
            $_SESSION['login_attempts'] = 0;
        }
    }
    $_SESSION['last_login_attempt'] = time();
 
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
 
        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            // Set Session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
 
            // Clear rate limiting on success
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_login_attempt']);

            // Redirect to role-based dashboard
            $dashboard_map = [
                'admin' => 'admin/dashboard.php',
                'landlord' => 'landlord/dashboard.php',
                'tenant' => 'tenant/dashboard.php'
            ];
            redirect($dashboard_map[$user['role']]);
 
        } else {
            // **This is what's happening:**
            $_SESSION['login_attempts']++;
            $_SESSION['error_flash'] = 'Invalid email or password.';
            redirect('login.php');
        }
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'An error occurred. Please try again later.';
        error_log('Login error: ' . $e->getMessage());
        redirect('login.php');
    }
}

$csrf_token = generate_csrf_token();

// --- Header ---
get_header(); 
?>

<div class="container">
    <div class="form-wrapper">
        <h2 class="text-center mb-4">Login to <?php echo SITE_NAME; ?></h2>
        
        <?php display_flash_messages(); ?>

        <form action="login.php" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-orange btn-lg">Login</button>
            </div>
        </form>
        
        <p class="text-center mt-4">
            Don't have an account? <a href="register.php" class="text-orange fw-bold text-decoration-none">Sign Up</a>
        </p>
    </div>
</div>

<?php
// --- Footer ---
get_footer(); 
?>