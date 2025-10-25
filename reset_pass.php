<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$new_password = 'password123'; // Change this if you want a different password
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

$users = $db->fetchAll("SELECT id, email FROM users");
foreach ($users as $user) {
    $db->update("UPDATE users SET password_hash = ? WHERE id = ?", [$password_hash, $user['id']]);
    echo "Updated password for: " . $user['email'] . "<br>";
}

echo "<p style='color: green;'>All passwords reset to: $new_password</p>";
echo "<p><a href='login.php'>Go to Login</a></p>";
?>