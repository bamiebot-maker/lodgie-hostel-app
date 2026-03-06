<?php
/**
 * config.example.php — Configuration Template
 *
 * COPY this file to config.php and fill in your real values.
 * DO NOT commit config.php to version control (it is in .gitignore).
 */

// --- Application ---
define('SITE_NAME', 'Lodgie');
define('SITE_CURRENCY_SYMBOL', '₦');

// --- Base URL (no trailing slash) ---
// Local example: 'http://localhost/lodgie'
// Production example: 'https://yourdomain.com'
define('BASE_URL', 'http://localhost/lodgie');

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'lodgie_db');
define('DB_USER', 'root');
define('DB_PASS', '');        // Change in production

// --- Paystack ---
// Get from: https://dashboard.paystack.com/#/settings/developers
define('PAYSTACK_PUBLIC_KEY', 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('PAYSTACK_SECRET_KEY', 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// --- Session ---
// Starts the session for all pages that include this config
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
