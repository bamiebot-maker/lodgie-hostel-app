<?php
/**
 * AJAX Endpoint: Mark Notification as Read
 *
 * Receives a JSON POST request with a notification ID and marks it as read.
 */

// Include core files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

// Set header to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get the POST body
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
    exit();
}

$notification_id = (int) $data['id'];
$user_id = (int) $_SESSION['user_id'];

// Mark as read
if (mark_notification_as_read($pdo, $notification_id, $user_id)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
}
?>