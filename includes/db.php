<?php
/**
 * Database Connection File
 *
 * Establishes a connection to the MySQL database using PDO.
 * This file is included in any script that needs database access.
 */

// We don't need to include config.php here if the file that
// includes db.php already includes config.php. But for
// robustness, we can use require_once.
require_once __DIR__ . '/config.php';

// Database Source Name (DSN)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

// PDO Options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
];

try {
    // Create the PDO database connection
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Handle connection error
    // In a real app, you'd log this and show a user-friendly error page.
    throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
}
?>