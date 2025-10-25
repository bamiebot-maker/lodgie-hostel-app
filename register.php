<?php
// --- Core Includes ---
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// --- Page-Specific Logic ---
$page_title = "Register";

// If user is already logged in, redirect them
if (is_logged_in()) {
    $role = $_SESSION['role'];
    if ($role === 'admin') redirect('admin/dashboard.php');
    if ($role === 'landlord') redirect('landlord/dashboard.php');
    if ($role === 'tenant') redirect('tenant/dashboard.php');
}

// Handle Registration Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request. Please try again.';
        redirect('register.php');
    }
 
    // 2. Sanitize and Validate Inputs
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);

    $errors = [];

    if (empty($name)) $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (!in_array($role, ['landlord', 'tenant'])) $errors[] = "Invalid role selected.";
    if (empty($phone)) $errors[] = "Phone number is required."; // Or make optional

    // 3. Check if email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error. Please try again.';
            error_log('Registration check error: ' . $e->getMessage());
        }
    }
    
    // 4. If no errors, create user
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
 
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $hashed_password, $role]);
            $user_id = $pdo->lastInsertId();
 
            // 5. Log the user in automatically
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['role'] = $role;
            
            $_SESSION['success_flash'] = 'Registration successful! Welcome to ' . SITE_NAME;
 
            // 6. Redirect to their new dashboard
            $dashboard = ($role === 'landlord') ? 'landlord/dashboard.php' : 'tenant/dashboard.php';
            redirect($dashboard);

        } catch (PDOException $e) {
            $_SESSION['error_flash'] = 'An error occurred during registration. Please try again.';
            error_log('Registration error: ' . $e->getMessage());
            redirect('register.php');
        }
    } else {
        // Store errors in session to display
        $_SESSION['error_flash'] = implode('<br>', $errors);
        redirect('register.php');
    }
}

// Get pre-selected role from URL (for "List Your Hostel" button)
$selected_role = isset($_GET['role']) && $_GET['role'] === 'landlord' ? 'landlord' : 'tenant';

// Generate CSRF token for the form
$csrf_token = generate_csrf_token();

// --- Header ---
get_header(); 
?>

<div class="container">
    <div class="form-wrapper">
        <h2 class="text-center mb-4">Create Your Account</h2>
        
        <?php display_flash_messages(); ?>

        <form action="register.php" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
                <div class="invalid-feedback">
                    Please enter your full name.
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div class="invalid-feedback">
                    Please enter a valid email address.
                </div>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" required>
                <div class="invalid-feedback">
                    Please enter your phone number.
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                    <div class="invalid-feedback">
                        Password must be at least 8 characters.
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <div class="invalid-feedback">
                        Passwords must match.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Register as a:</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="role" id="roleTenant" value="tenant" <?php echo ($selected_role === 'tenant') ? 'checked' : ''; ?> required>
                    <label class="form-check-label" for="roleTenant">
                        Tenant / Student (I'm looking for a hostel)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="role" id="roleLandlord" value="landlord" <?php echo ($selected_role === 'landlord') ? 'checked' : ''; ?> required>
                    <label class="form-check-label" for="roleLandlord">
                        Landlord (I want to list a hostel)
                    </label>
                </div>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-orange btn-lg">Create Account</button>
            </div>
        </form>
        
        <hr class="my-4">
        
        <p class="text-center">
            Already have an account? <a href="login.php" class="text-orange fw-bold text-decoration-none">Login</a>
        </p>
    </div>
</div>

<?php
// --- Footer ---
get_footer(); 
?>