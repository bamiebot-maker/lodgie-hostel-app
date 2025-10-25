<?php
/**
 * Logout Script
 *
 * Destroys the user session and redirects to the homepage.
 */

// Include config to ensure session_start() is called
require_once 'includes/config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to the homepage
// Use the BASE_URL from config.php
header('Location: ' . BASE_URL . '/index.php');
exit();
?>