<?php
/**
 * Authentication Logic
 *
 * This file is not meant to be included directly.
 * The logic here should be integrated into login.php, register.php, and logout.php
 *
 * Example logic for login:
 * * if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 * require_once 'includes/db.php';
 * require_once 'includes/helpers.php';
 * * // 1. Verify CSRF Token
 * if (!verify_csrf_token($_POST['csrf_token'])) {
 * $_SESSION['error_flash'] = 'Invalid request. Please try again.';
 * redirect('login.php');
 * }
 * * // 2. Sanitize Inputs
 * $email = sanitize($_POST['email']);
 * $password = $_POST['password']; // Don't sanitize password input
 * * // 3. Find user
 * $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
 * $stmt->execute([$email]);
 * $user = $stmt->fetch();
 * * // 4. Verify password
 * if ($user && password_verify($password, $user['password'])) {
 * // 5. Set Session
 * session_regenerate_id(true); // Prevent session fixation
 * $_SESSION['user_id'] = $user['id'];
 * $_SESSION['user_name'] = $user['name'];
 * $_SESSION['user_email'] = $user['email'];
 * $_SESSION['role'] = $user['role'];
 * * // 6. Redirect to role-based dashboard
 * $dashboard_map = [
 * 'admin' => 'admin/dashboard.php',
 * 'landlord' => 'landlord/dashboard.php',
 * 'tenant' => 'tenant/dashboard.php'
 * ];
 * redirect($dashboard_map[$user['role']]);
 * * } else {
 * $_SESSION['error_flash'] = 'Invalid email or password.';
 * redirect('login.php');
 * }
 * }
 *
 *
 * Example logic for registration:
 * * if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 * // ... CSRF and sanitization ...
 * $name = sanitize($_POST['name']);
 * $email = sanitize($_POST['email']);
 * $password = $_POST['password'];
 * $confirm_password = $_POST['confirm_password'];
 * $role = sanitize($_POST['role']);
 * * // ... Validation (check if passwords match, email format, etc.) ...
 * * // Check if email exists
 * $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
 * $stmt->execute([$email]);
 * if ($stmt->fetch()) {
 * $_SESSION['error_flash'] = 'An account with this email already exists.';
 * redirect('register.php');
 * }
 * * // Hash password
 * $hashed_password = password_hash($password, PASSWORD_BCRYPT);
 * * // Insert user
 * $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
 * $stmt->execute([$name, $email, $hashed_password, $role]);
 * $user_id = $pdo->lastInsertId();
 * * // Log the user in automatically
 * $_SESSION['user_id'] = $user_id;
 * $_SESSION['user_name'] = $name;
 * $_SESSION['user_email'] = $email;
 * $_SESSION['role'] = $role;
 * * // Redirect to their new dashboard
 * $dashboard = ($role === 'landlord') ? 'landlord/dashboard.php' : 'tenant/dashboard.php';
 * redirect($dashboard);
 * }
 */

// This file is intentionally left as a "logic guide"
// as the actual implementation lives inside login.php and register.php
?>