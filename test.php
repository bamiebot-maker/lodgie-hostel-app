<?php
/**
 * Admin Password Reset Tool
 *
 * IMPORTANT: DELETE THIS FILE AFTER USE!
 *
 * 1. Place this file in your project's root folder (e.g., /lodgie/test.php)
 * 2. Run it by visiting http://localhost/lodgie/test.php in your browser.
 * 3. Log in with the new credentials.
 * 4. DELETE THIS FILE.
 */

// Include database connection
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Lodgie Admin Password Reset Tool</h1>";

try {
    // --- DEFINE YOUR NEW PASSWORD HERE ---
    // You can change 'admin123' to any new password you want.
    $new_password = 'admin123'; 
    
    // Generate the hash
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    if (!$hashed_password) {
        die("<p style='color: red;'>Error: password_hash() function failed. Is your PHP version 5.5 or newer?</p>");
    }

    echo "<p>Attempting to set password to: '<strong>" . $new_password . "</strong>'</p>";

    // Prepare the update query
    $email_to_update = 'admin@lodgie.com';
    $sql = "UPDATE users SET password = ? WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    
    // Execute the update
    $stmt->execute([$hashed_password, $email_to_update]);
    
    // Check if any rows were actually updated
    if ($stmt->rowCount() > 0) {
        echo "<h2 style='color: green;'>SUCCESS!</h2>";
        echo "<p>The password for '<strong>" . $email_to_update . "</strong>' has been reset.</p>";
        echo "<p>You can now log in with:</p>";
        echo "<ul>";
        echo "<li><strong>Email:</strong> " . $email_to_update . "</li>";
        echo "<li><strong>Password:</strong> " . $new_password . "</li>";
        echo "</ul>";
    } else {
        echo "<h2 style='color: red;'>UPDATE FAILED!</h2>";
        echo "<p>No user with the email '<strong>" . $email_to_update . "</strong>' was found in the database.</p>";
        echo "<p>Please check your `users` table. If the admin user doesn't exist, you must insert it:</p>";
        echo "<pre style='background: #eee; padding: 10px; border-radius: 5px;'>";
        echo "INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES ('Admin User', 'admin@lodgie.com', '" . $hashed_password . "', 'admin');";
        echo "</pre>";
    }

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>DATABASE ERROR!</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3 style='color: orange; font-weight: bold;'>!!! IMPORTANT !!!</h3>";
echo "<p>For security, please DELETE THIS FILE (<code>test.php</code>) FROM YOUR SERVER NOW.</p>";
?>